# WPHooks Lifecycle Guide

**Purpose**: Trace discovery, registration, execution, activation, and teardown for attribute-based WordPress hooks.

## 1. Discover and cache

Call `WPHooksScanner::getHookRegistrations()` from the bootstrap path. It first checks the request-local cache, then an existing `WPHooksCache.php`; otherwise it scans RobotLoader-indexed classes. Only declared, non-static methods and properties are eligible. Successful scans are memoized and optionally exported as arrays plus closure snapshots.

## 2. Build the registration plan

`WPHooksContainerRegistry::initialize()` calls `registerAll()` once. For each registration it:

- skips classes absent from the container;
- resolves a string hook directly or evaluates a closure hook through `WPHookPlanProvider`;
- evaluates `registerIf` once, before the hook enters either pool;
- resolves static or callable tags and normalizes them to unique strings;
- creates a lazy method or property handler;
- stores the entry in the active pool or `DeferredHookManager` when `defer: true`.

Errors in hook-name resolution, gates, or tags are logged and the individual registration is skipped.

## 3. Register with WordPress

Active entries are registered with `add_action()` or `add_filter()` using the declared priority and accepted argument count. `initialize()` is idempotent. Deferred entries are not registered until an activation selector matches them.

```php
$registry->initialize();
$registry->activateDeferredByHook('init');
$registry->activateDeferredByTags(['cache', 'seo']);
```

Available deferred selectors include hook, class, namespace, callable target, and tags. A deferred `registerIf` gate is re-evaluated during activation; a false gate leaves the entry deferred.

## 4. Execute at hook fire

`ContainerLazyHookHandler` resolves the service only when WordPress invokes it. It evaluates `executeIf` with the container and matching hook arguments, then invokes public methods directly or binds a closure for protected/private methods. `ContainerLazyPropertyHookHandler` reads the property at fire time and invokes the resulting closure/invokable object. The plan provider avoids reflection on the hot path when a cacheable plan exists.

If a gate returns false, a filter receives its original first argument unchanged. If invocation throws, the error is logged; filters still pass through the original value and actions return `null`.

## 5. Runtime and anonymous-object path

Use `WPHooksRuntimeRegistry` when the object already exists or cannot be discovered from files. `registerHooksOn()` scans attributes once per object and registers supported hooks immediately. Attribute closure hook names and attribute conditions are skipped because this path has no container; manual `registerAction()` / `registerFilter()` support closures, captured state, explicit owners, and direct `executeIf` conditions. `AnonClassHookPropertyAbstract` stores a parent class/property pair so the container target resolver can identify an anonymous property value without a call-stack search.

## 6. Teardown

Use `unregisterByHook()`, `unregisterByClass()`, `unregisterByNamespace()`, `unregisterByCallable()`, or `unregisterByTags()` for active container handlers. Use the matching deferred unregister methods for the deferred pool. `unregisterHooksOn($object)` removes all runtime records for one owner and clears its `WeakMap` state.

## Constraints to remember

- Inherited members and static members are intentionally ignored by the shared scanner.
- `registerIf` is registration lifecycle state; `executeIf` is fire-time state.
- Named handler identity matters for reliable `remove_action()` / `remove_filter()` calls.
- Dynamic callable plans need container-resolvable class parameters or defaults; unsupported defaults fall back to reflection.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/WPHooksScanner.php` — discovery, memoization, and cache export.
- `server/Core/Container/Support/WPHooks/WPHookPlanProvider.php` — callable plans, gates, dynamic names, and tag resolution.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — active/deferred lifecycle and selectors.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksRuntimeRegistry.php` — immediate object and manual registration.
- `server/Core/Container/Support/WPHooks/Invoker.php` — execution and filter fallback behavior.
