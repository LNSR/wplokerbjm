<!-- Context: project-intelligence/wphooks-ownership | Priority: high | Version: 1.0 | Updated: 2026-11-18 -->

# WPHooks Ownership & RAII

**Purpose**: How the hook subsystem decides who owns a registration, when it dies, and how cleanup stays deterministic without leak-prone manual bookkeeping.

## Core Rule — One Strong Owner

`$wp_filter` (the WordPress hook table) is the **only strong owner of an active invoker**. Registries keep their bookkeeping alive, but every registry→invoker reference is weak — so an external `remove_action()` / `remove_all_*` is authoritative and self-healing.

| Side | Holds invoker | Holds entry | Released by |
|---|---|---|---|
| `$wp_filter` | **strong** (sole owner) | — | `remove_action/filter`, `remove_all_*`, WP reset |
| Active entry (`HandlerEntryTrait::$handler`) | `WeakReference` (setter wraps objects) | registry holds entry strongly | invoker GC → entry destructor |
| `$entriesByInvokers` | `WeakMap` key | value | key GC |
| `QueuedHooksCleaner::$entries` | `WeakMap` key | value | key GC |
| Deferred pool (`DeferredHookEntryHandler`) | **strong** (readonly promoted) | pool holds entry strongly | activation or pool release |

Deferred is the deliberate exception: the pool must own the invoker until the trigger fires (or the entry is released), otherwise it could vanish while parked.

## RAII Chain (active registration)

Destruction is the release signal — no explicit "close" is required:

1. WordPress drops the callback (`remove_action`, `remove_all_*`, teardown) → invoker refcount hits zero → `HookInvokerTrait::__destruct()`.
2. Destructor: `isDestructed = true` → `onUnregisterDoCallback()` (user cleanup side effect) → `removeCallback($invoker, true)` → callbacks nulled.
3. Registry callback removes its bookkeeping: container `$handlers[key]` / instance `$weakRegistry[$owner]` + `$entriesByInvokers`, and schedules index removal.
4. `ContainerRegistryHandlerEntry::__destruct()` unregisters if still registered, then removes all `EntriesIndexer` buckets.
5. Guards are idempotent: `consumed`, `removed`, `isDestructed`.

`onUnregisterDo` therefore runs exactly once per released registration — after once-consume, explicit unregister, owner GC, or raw `remove_action`. It is skipped once `shutdown` has fired (or is firing) — teardown at that point is expected — and a throwing cleanup is logged, never propagated.

## Lifetime Scopes

- **Container path**: the invoker stores `ContainerInterface` + `class`/`method` strings — never the service instance. Services stay collectable between fires; resolution happens per invocation.
- **Runtime path**: the owner is a `WeakReference`. On each dispatch, `instanceRef->get() === null` → `consumeLifetimeAndClean()` (declare the hook dead + remove it), filters pass through. `unregisterHooksOn($owner)` is the deterministic teardown; `$scanned` is a `WeakMap` so the scan-once guard dies with the owner.
- **Deferred pool**: releasing an entry destroys `DeferredHookEntryHandler` → removes its index buckets **and** its auto-scheduler callback (the `PHP_INT_MIN` trigger listener). Releasing is therefore index- and listener-clean, not just "forget the object".

## Once-Hooks & Nested Dispatch

`once` consumes at the start of the body (`consumed = true`) so re-entrancy passes through. WordPress cannot remove a callback mid-dispatch (core #64653), so the registry queues the entry in `QueuedHooksCleaner::$queuedRemovalEntry` and finishes removal via an `all` listener.

Nested anticipation: when an inner handler runs, the cleaner immediately unregisters previously queued **outer** entries (skipping the currently dispatching invoker) — outer hooks cannot re-enter, and the queue drains across dispatch levels.

## No-Leak Rules

- Never store invokers, entries, or closures in long-lived arrays/maps — use `HookKey`, unique-key strings, or `spl_object_id` instead.
- `WPHookPlanProvider` caches (`$boundClosureCache`, `$callablePlanCache`), `InstanceRegistryRuntimeHookResolver::$weakMap`, and `HookTargetResolverTrait::$callableTargetCache` are `WeakMap`s keyed by the source closure/object — derived data dies with its source.
- `WPHooksInstanceObjectCache` is derived metadata: flushed on destruct when dirty, safe to delete and regenerate. It is never a source of truth.
- `QueuedHooksCleaner::$queuedRemovalEntry` **is** strong; it is transient (drains during dispatch) — do not treat it as storage.
- Prefer selective teardown (`unregisterByHook/Class/Callable/Tags`, `unregisterDeferredBy*`, `unregisterHooksOn`). `remove_all_*` works and self-heals via destructors, but is blunt and skips selector semantics.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Trait/HookInvokerTrait.php` — destructor, `consumed`/`removed` guards, cleanup callback.
- `server/Core/Container/Support/WPHooks/Trait/HandlerEntryTrait.php` — weak invoker getter/setter.
- `server/Core/Container/Support/WPHooks/Registry/HookEntryHandlers.php` — active/deferred entries, owner weakrefs, destructors.
- `server/Core/Container/Support/WPHooks/Registry/DeferredManager/DeferredHookEntryHandler.php` — strong deferred ownership + index/scheduler release.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — `$handlers`, `$entriesByInvokers`, `$removeEntry`.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php` — `$weakRegistry`, `$scanned`, `$removeRuntimeHook`, `unregisterHooksOn`.
- `server/Core/Container/Support/WPHooks/Invoker/ContainerLazyHookInvoker.php` / `RuntimeHookInvoker.php` — lazy vs weak-owner execution.
- `server/Core/Container/Support/WPHooks/Janitor/QueuedHooksCleaner.php` — once/GC removal queue + nested anticipation.
- `server/Core/Container/Support/WPHooks/Indexers/EntriesIndexer.php` — index buckets released on entry destruction.

## Related Files

- `technical-domain.md` — WPHooks subsystem map and hook attribute surface
- `navigation.md` — context index and quick routes
