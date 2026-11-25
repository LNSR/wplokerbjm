<!-- Context: backend/guides | Priority: high | Version: 1.1 | Updated: 2026-11-27 -->

# WPHooks Lifecycle Guide

**Purpose**: Trace discovery, registration, execution, activation, and teardown for attribute-based WordPress hooks.

## 1. Discover and cache

Call `WPHooksScanner::getHookRegistrations()` from the bootstrap path. It first checks the request-local cache, then `WPhooksRegistryContainerCache.php`; otherwise it scans candidates from the RobotLoader index merged with `getKnownEntryNames()` and filtered by `container->has()`. Only declared, non-static methods and properties are eligible. Successful scans are memoized and exported as serialized registrations with closure snapshots.

## 2. Build the registration plan

`WPHooksContainerRegistry::initialize()` asks its injected scanner for registrations, then calls `registerAll()` once. For each registration it:

- skips classes absent from the container;
- resolves a string hook directly or evaluates a closure hook through `WPHookPlanProvider`;
- evaluates `registerIf` once before the hook enters either pool — skipped when `deferRegisterUntilHook` is set, so the gate is re-evaluated at activation time instead;
- resolves static or callable tags and normalizes them to unique strings;
- builds a `HookInvokerConfig` + `HookInvoker` for the matching `Invoker\Target`;
- stores the entry in the active pool or `ContainerRegistryDeferredHookManager` when `deferRegister` or `deferRegisterUntilHook` is set.

Errors in hook-name resolution, gates, or tags are logged through the injected `HookLoggerInterface` and only that registration is skipped.

## 3. Register with WordPress

Active entries are registered with `add_action()` / `add_filter()` using the declared priority and accepted argument count. `initialize()` is idempotent. Deferred entries are not registered until an activation selector matches them.

```php
$registry->initialize();
$registry->activateDeferredByHook('init');
$registry->activateDeferredByTags(['cache', 'seo']);
```

- `once` entries consume on first evaluation via `HookInvokerTrait::consumeOnceAndClean()` → `HookRemovalController::consumeOnce()` → registry removal callback → `QueuedHooksCleaner`. They are not retried.
- While a handler is dispatching, removal is queued until the `all` boundary (`#61263`); nested/adjacent `once` entries are anticipated (`#64653`). `QueuedHooksCleaner` is the queue owner.
- `deferRegisterUntilHook` attaches a `PHP_INT_MIN` filter listener to the trigger. If the trigger already fired (`did_action`/`did_filter`), activation happens immediately; otherwise the listener activates and detaches on the first successful activation. A failing `registerIf` leaves the entry parked for a later attempt.
- Wildcard selectors (`activateDeferredByHookPattern`, `unregisterByTagPattern`, ...) follow `HookPattern`: exactly one trailing asterisk with a literal prefix of at least two characters.

## 4. Execute at hook fire

`HookInvoker` is the single runtime entry point. Its `InvocationTarget` strategy resolves the container service or live owner only when WordPress invokes it. `HookInvoker` evaluates `executeIf` with the container and named hook arguments, then invokes the method or property callable. Plans avoid reflection on the hot path when a cacheable plan exists.

If a gate returns false, a filter receives its original first argument unchanged. If invocation throws, the error is logged through `HookLoggerInterface`; filters pass through the original value and actions return `null`.

## 5. Runtime and anonymous-object path

Use `WPHooksInstanceRegistry` when the object already exists or cannot be discovered from files. `registerHooksOn()` scans attributes once per object and registers supported hooks immediately.

- Attribute closures (hook name, `registerIf`, `executeIf`) resolve through the DI-container-backed `WPHookPlanProvider`: parameters inject by name from hook args first, then the container, then defaults.
- Attribute-argument closures must be static closures (PHP 8.1 constant-expression rule); private members resolve via `self::`, no instance binding is needed.
- Plain `deferRegister` holds the entry in the owner's deferred bucket until `activateDeferredByCallableInLiveInstance()` or another owner-scoped selector activates it; `deferRegisterUntilHook` auto-activates when the trigger fires (`did_action` immediate path or `PHP_INT_MIN` listener), retrying on each fire while `registerIf` rejects.
- Runtime entries live in `WeakMap<owner, entries>`; sibling instances are isolated. When the owner is collected, its bucket and scheduler callback are released immediately.
- Static members are silently skipped. `HookInvokerTrait::$dispatchDepth` (with `dispatching`) tracks reentrancy for safe cleanup.
- `ModuleHookMetadata` stores `outerClassString`, `identifier`, and `ownershipPath` so InstanceRegistry can cache stable anonymous/inner identity.

## 6. Teardown

Use `unregisterByHook()`, `unregisterByClass()`, `unregisterByNamespace()`, `unregisterByCallable()`, `unregisterByTags()`, or the wildcard `unregisterByHookPattern()` / `unregisterByTagPattern()` for active container handlers. Use the matching `unregisterDeferredBy*` methods for the deferred pool. `unregisterHooksOn($object)` removes all runtime records for one owner (including its deferred bucket) and clears its `WeakMap` state. `QueuedHooksCleaner` removes its `all` listener on destruction.

## Constraints to remember

- Inherited members and static members are intentionally ignored by the shared scanner.
- `registerIf` is registration lifecycle state; `executeIf` is fire-time state.
- Named handler identity matters for reliable `remove_action()` / `remove_filter()` calls.
- Dynamic callable plans need container-resolvable class parameters or defaults; unsupported defaults fall back to reflection.
- `HookLoggerInterface` is the only logging seam for WPHooks; `ModuleHookMetadata` is the intentional shared-logger exception until decoupled.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/WPHooksScanner.php` — discovery, memoization, and cache export.
- `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` — façade over plan/condition/debug collaborators.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — active/deferred lifecycle and selectors.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php` — immediate object registration and owner-scoped selectors.
- `server/Core/Container/Support/WPHooks/Invoker/HookInvoker.php` — unified execution path; `Invoker/HookRemovalController.php` — once/lifetime cleanup.
- `server/Core/Container/Support/WPHooks/Janitor/QueuedHooksCleaner.php` — dispatch-safe removal queue.
- `server/Core/Container/Support/WPHooks/Logger/` — logger contract and adapters.
