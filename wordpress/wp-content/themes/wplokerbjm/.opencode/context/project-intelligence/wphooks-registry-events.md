<!-- Context: project-intelligence/wphooks-registry-events | Priority: high | Version: 1.3 | Updated: 2026-11-27 -->

# WPHooks Registry Event Bridge

**Purpose**: How application code drives the hook registries through WordPress events — the control-plane adapter moved out of the old `Core\Wordpress\*HookEvent` classes and into the registries themselves.

## Why It Lives in the Registry

The bridge is control-plane, not an application hook. Keeping it inside the registry means:

- no extra classes to scan/cache/instantiate;
- selectors (`activateDeferredBy*`, `unregisterBy*`, `unregisterHooksOn`) are reachable from plain WordPress code without injecting the registry;
- no static accessors — the IoC upstream resolves the registry when needed;
- array callables (`[$this, 'method']`) stay removable; first-class callables would create a new closure per call and make the bridge unregistrable.

## Two Bridge Modes

| Registry | Default registration | Raw bridge |
|---|---|---|
| `WPHooksContainerRegistry` | No bridge boot: `initialize()` only pulls `scanner->getHookRegistrations()` and indexes handlers | `initEventBridge()` explicit; internal/test/standalone only. `removeEventBridge()` tears down |
| `WPHooksInstanceRegistry` | `#[Action]` on `REGISTER_HOOKS`; `#[Filter]` on the remaining API methods — scanner registers them as lazy invokers | `initEventBridge()` explicit; standalone/tests only. `removeEventBridge()` tears down |

The container registry has no hook attributes of its own, so `initialize()` never calls `initEventBridge()`. The instance registry's attributes are the production path; its raw bridge is only for callers that use the registry without the container scan.

**Toggle**: both registries expose a reversible pair — `initEventBridge()` registers listeners (no-op when already up), `removeEventBridge()` removes them and flips `$bridgeInitialized` back to `false`. Removal is exact (same array callables, priority 10) and leaves application hooks untouched.

## Event Map

Both registries expose `EVENT_BRIDGE_HOOKS` — `event name → registry method` — as the single source of truth (used by `initEventBridge()` and tests).

- `WPHooksContainerRegistry::EVENT_BRIDGE_HOOKS` — **21 entries**: 7 `ACTIVATE_DEFERRED_BY_*`, 7 `UNREGISTER_DEFERRED_BY_*`, 7 `UNREGISTER_BY_*`.
- `WPHooksInstanceRegistry::EVENT_BRIDGE_HOOKS` — **20 entries**: `REGISTER_HOOKS` / `UNREGISTER_HOOKS`, active and deferred hook/pattern/tag/class/callable selectors, and the deferred class/callable scopes.

Selectors that take `($instance, $selector)` use `EVENT_BRIDGE_ACCEPTED_ARGS` (2 accepted arguments); `REGISTER_HOOKS` accepts 3. Event names are constants (`wphooks_container_*`, `wphooks_instance_*`) — never hardcode strings.

## Emitting

```php
// Container: activate deferred work for a class, then drop its active hooks.
\do_action(WPHooksContainerRegistry::ACTIVATE_DEFERRED_BY_CLASS, self::class);
\do_action(WPHooksContainerRegistry::UNREGISTER_BY_CALLABLE, [$this, __FUNCTION__]);

// Runtime: register / release an attribute-bearing module object.
\do_action(WPHooksInstanceRegistry::REGISTER_HOOKS, $module);
\do_action(WPHooksInstanceRegistry::UNREGISTER_HOOKS, $module);
```

The dispatcher needs nothing but the constant — no registry reference, no container lookup.

Runtime deferred scopes come in two granularities:

| Scope | API | Effect |
|---|---|---|
| Callable | `ACTIVATE_DEFERRED_BY_CALLABLE_IN_LIVE_INSTANCE` / `UNREGISTER_DEFERRED_BY_CALLABLE_IN_LIVE_INSTANCE` | one member (all priorities/attributes) of a live instance |
| Class | `ACTIVATE_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE` / `UNREGISTER_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE` | every deferred entry owned by the given live instance (all members) |

Runtime "class" scope is per **instance** (`spl_object_id` ownership): passing one object never wakes deferred entries of a sibling instance of the same class.

## Control-Plane Guarantees

- Bridge listeners are **not** in `$handlers`, `$weakRegistry`, `$entriesByInvokers`, or `EntriesIndexer` — selector sweeps cannot remove the control plane.
- Bridged calls land on the registry's public API, so ownership/RAII semantics still apply (`registerIf` re-evaluation, deferred indexes, `onUnregisterDo`, once-cleanup).
- Instance deferred entries live in an owner-keyed `WeakMap`; owner GC drops that bucket and its auto-scheduler before a bridged selector can see it.
- Consumers updated to registry constants: `PluginsManager`, `ModuleHookMetadata`, `GlobalHooks`, `CachePurgeHandler`, `RedirectHooks`, `CloudflareCachePurger`, `GraphQLHeadersPolicy`, `SearchJobHooks`, `WPGraphQL`, `RankMath`, `Litespeed`, `MetaBox`.
- Tests use `WplokerbjmTestCase::registeredApplicationHooks()` — registered hooks minus both bridges — when asserting "nothing was registered".

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — `EVENT_BRIDGE_HOOKS` (21), explicit `initEventBridge()` / `removeEventBridge()`, `initialize()` scanner path.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php` — `EVENT_BRIDGE_HOOKS` (20), `#[Action]`/`#[Filter]` methods, `EVENT_BRIDGE_ACCEPTED_ARGS`, explicit bridge toggle.
- `server/Core/Container/Init.php` — bootstrap calls `WPHooksContainerRegistry::initialize()` (no bridge boot).
- `tests/WPHookTests/Registry/EventBridgeTest.php` — idempotent boot, deferred activation via event, bridge survives sweeps, standalone runtime bridge.
- `tests/Support/WplokerbjmTestCase.php` — `registeredApplicationHooks()`.

## Related Files

- `technical-domain.md` — WPHooks subsystem map and hook attribute surface
- `wphooks-ownership-raii.md` — strong/weak ownership and cleanup chain
- `wphooks-logger.md` — injected logger contract and DI wiring
- `navigation.md` — context index and quick routes
