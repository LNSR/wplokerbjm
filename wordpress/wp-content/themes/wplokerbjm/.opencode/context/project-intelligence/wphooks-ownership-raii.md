<!-- Context: project-intelligence/wphooks-ownership | Priority: high | Version: 1.1 | Updated: 2026-11-27 -->

# WPHooks Ownership & RAII

**Purpose**: How the hook subsystem decides who owns a registration, when it dies, and how cleanup stays deterministic without leak-prone manual bookkeeping.

## Core Rule — One Strong Owner

`$wp_filter` (the WordPress hook table) is the **only strong owner of an active invoker**. Registries keep their bookkeeping alive, but every registry→invoker reference is weak — so an external `remove_action()` / `remove_all_*` is authoritative and self-healing.

| Side | Holds invoker | Holds entry | Released by |
|---|---|---|---|
| `$wp_filter` | **strong** (sole owner) | — | `remove_action/filter`, `remove_all_*`, WP reset |
| Active entry (`HandlerEntryTrait::$handler`) | `WeakReference` (setter wraps objects) | registry holds entry strongly | invoker GC → entry destructor |
| `$entriesByInvokers` | `WeakMap<HookInvoker, WeakReference<Entry>>` | weakref value | invoker/record GC |
| `QueuedHooksCleaner::$queuedRemovalEntry` | `WeakMap` keyed by `HookInvoker` | transient entry value | drain or GC |
| Container deferred pool (`ContainerRegistryDeferredHookManager`) | **strong** in flat array | pool holds entry strongly | activation or pool release |
| Instance deferred pool (`InstanceRegistryDeferredHookManager`) | **strong** inside owner bucket | `WeakMap<owner, array<string, entry>>` | activation, owner GC, or bucket release |

Deferred is the deliberate exception: the pool must own the invoker until the trigger fires (or the entry is released), otherwise it could vanish while parked. The instance pool uses an owner-keyed `WeakMap`, so releasing the owner drops its entire bucket immediately.

## RAII Chain (active registration)

Destruction is the release signal — no explicit "close" is required:

1. WordPress drops the callback (`remove_action`, `remove_all_*`, teardown) → invoker refcount hits zero → `HookInvokerTrait::__destruct()`.
2. Destructor: `isDestructed = true` → `onUnregisterDoCallback()` (user cleanup side effect) → `removeCallback($invoker, true)` → callbacks nulled.
3. Registry callback removes its bookkeeping: container `$handlers[key]` / instance `$weakRegistry[$owner]` + weak `$entriesByInvokers`, and schedules index removal.
4. `HandlerEntryTrait` entry destructor unregisters if still registered, then removes its `EntriesIndexer` buckets. Deferred entries also remove their auto-scheduler listener.
5. Guards are idempotent: `consumed`, `removed`, `isDestructed`.

`onUnregisterDo` therefore runs exactly once per released registration — after once-consume, explicit unregister, owner GC, or raw `remove_action`. It is skipped once `shutdown` has fired (or is firing) — teardown at that point is expected — and a throwing cleanup is logged, never propagated.

## Lifetime Scopes

- **Container path**: the invoker stores `ContainerInterface` + `class`/`method` strings — never the service instance. Services stay collectable between fires; resolution happens per invocation.
- **Runtime path**: the owner is a `WeakReference`. On each dispatch, `instanceRef->get() === null` → `consumeLifetimeAndClean()` (declare the hook dead + remove it), filters pass through. `unregisterHooksOn($owner)` is the deterministic teardown; `$scanned` is a `WeakMap` so the scan-once guard dies with the owner.
- **Deferred storage**: `DeferredHooksTrait` exposes abstract `deferredGet/Put/Remove/Count/Entries` + `afterDeferredAdded`. Container manager keeps a flat array + `EntriesIndexer`; instance manager keeps `WeakMap<owner, entries>`, prunes dead owners, and schedules triggers with the owner `WeakReference`. Releasing an entry removes its index buckets **and** its auto-scheduler callback.
- **Invoker + dispatch depth**: `Invoker/HookInvoker.php` is the unified invoker; `Invoker/Target/*` holds container/runtime method/property targets. `HookInvokerTrait::$dispatchDepth` tracks re-entrant dispatch and feeds `HandlerEntryTrait::isCurrentlyExecuting()`.

## Once-Hooks & Nested Dispatch

`once` consumes at the start of the body (`consumed = true`) so re-entrancy passes through. WordPress cannot remove a callback mid-dispatch (core #64653), so the registry queues the entry in `QueuedHooksCleaner::$queuedRemovalEntry` via `scheduleCleanUpCallback()` and finishes removal through an `all` listener.

Nested anticipation: when an inner handler runs, the cleaner immediately unregisters previously queued **outer** entries (skipping the currently dispatching invoker) — outer hooks cannot re-enter, and the queue drains across dispatch levels.

## No-Leak Rules

- Never store invokers, entries, or closures in long-lived arrays/maps — use `HookKey`, unique-key strings, or `spl_object_id` instead.
- `WPHookPlanProvider` caches, `InstanceRegistryRuntimeHookResolver::$weakMap`, and `HookTargetResolverTrait::$callableTargetCache` are `WeakMap`s keyed by the source closure/object — derived data dies with its source.
- `WPHooksInstanceObjectCache` is derived metadata: flushed on destruct when dirty, safe to delete and regenerate. It is never a source of truth.
- `QueuedHooksCleaner::$queuedRemovalEntry` is a `WeakMap`, not storage: entries are only transiently strong while the handler is alive; the queue drains during dispatch.
- Prefer selective teardown (`unregisterByHook/ByClass/ByCallable/ByTags`, `unregisterDeferredBy*`, `unregisterHooksOn`). `remove_all_*` works and self-heals via destructors, but is blunt and skips selector semantics.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Trait/HookInvokerTrait.php` — destructor, `consumed`/`removed` guards, dispatch depth, cleanup callback.
- `server/Core/Container/Support/WPHooks/Trait/HandlerEntryTrait.php` — weak invoker getter/setter and `isCurrentlyExecuting()`.
- `server/Core/Container/Support/WPHooks/Trait/DeferredHooksTrait.php` — owner-aware deferred storage and activation helpers.
- `server/Core/Container/Support/WPHooks/Registry/Handlers/ContainerRegistryHandlerEntry.php` + `RuntimeRegistryHandlerEntry.php` — active/deferred entries, owner weakrefs, destructors.
- `server/Core/Container/Support/WPHooks/Registry/DeferredManager/Handlers/DeferredHookEntryHandler.php` — strong deferred ownership + index/scheduler release.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — `$handlers`, weak `$entriesByInvokers`, `$removeEntry`.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php` — `$weakRegistry`, `$scanned`, weak `$entriesByInvokers`, `unregisterHooksOn`.
- `server/Core/Container/Support/WPHooks/Invoker/HookInvoker.php` + `Invoker/Target/*` — unified lazy vs weak-owner execution.
- `server/Core/Container/Support/WPHooks/Janitor/QueuedHooksCleaner.php` — once/GC removal queue + nested anticipation.
- `server/Core/Container/Support/WPHooks/Indexers/EntriesIndexer.php` — index buckets released on entry destruction.

## Related Files

- `technical-domain.md` — WPHooks subsystem map and hook attribute surface
- `wphooks-registry-events.md` — registry event bridge and control-plane selectors
- `wphooks-logger.md` — injected logger contract and DI wiring
- `navigation.md` — context index and quick routes
