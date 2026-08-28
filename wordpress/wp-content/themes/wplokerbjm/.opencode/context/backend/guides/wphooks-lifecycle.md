# WPHooks Lifecycle Guide

**Purpose**: Trace discovery, registration, execution, activation, and teardown for attribute-based WordPress hooks.

## 1. Discover and cache

Call `WPHooksScanner::getHookRegistrations()` from the bootstrap path. It first checks the request-local cache, then an existing `WPHooksCache.php`; otherwise it scans RobotLoader-indexed classes. Only declared, non-static methods and properties are eligible. Successful scans are memoized and optionally exported as arrays plus closure snapshots.

## 2. Build the registration plan

`WPHooksContainerRegistry::initialize()` calls `registerAll()` once. For each registration it:

- skips classes absent from the container;
- resolves a string hook directly or evaluates a closure hook through `WPHookPlanProvider`;
- evaluates `registerIf` once, before the hook enters either pool — skipped when `deferRegisterUntilHook` is set, so the gate is re-evaluated at activation time instead;
- resolves static or callable tags and normalizes them to unique strings;
- creates a lazy method or property handler;
- stores the entry in the active pool or `DeferredHookManager` when `deferRegister` or `deferRegisterUntilHook` is set.

Errors in hook-name resolution, gates, or tags are logged and the individual registration is skipped.

## 3. Register with WordPress

Active entries are registered with `add_action()` or `add_filter()` using the declared priority and accepted argument count. `initialize()` is idempotent. Deferred entries are not registered until an activation selector matches them.

```php
$registry->initialize();
$registry->activateDeferredByHook('init');
$registry->activateDeferredByTags(['cache', 'seo']);
```

- `once` entries remove themselves after the first evaluation (`removeOnceEntry`), regardless of the gate result.
- `deferRegisterUntilHook` implies deferral: `scheduleDeferredActivation()` attaches a `PHP_INT_MIN` listener on the trigger hook. If the trigger already fired (`did_action`), activation is queued in `pendingDeferredActivation` and runs after the active loop. `executeIf` is evaluated at activation only when every parameter is container-resolvable (`executeIfResolvable`); otherwise it stays a per-fire guard inside the handler.
- Wildcard selectors (`activateDeferredByHookPattern`, `unregisterByTagPattern`, ...) follow `HookPattern` rules: exactly one trailing asterisk with a prefix of ≥ 2 chars.

## 4. Execute at hook fire

`ContainerLazyHookHandler` resolves the service only when WordPress invokes it. It evaluates `executeIf` with the container and matching hook arguments, then invokes public methods directly or binds a closure for protected/private methods. `ContainerLazyPropertyHookHandler` reads the property at fire time and invokes the resulting closure/invokable object. The plan provider avoids reflection on the hot path when a cacheable plan exists.

If a gate returns false, a filter receives its original first argument unchanged. If invocation throws, the error is logged; filters still pass through the original value and actions return `null`.

## 5. Runtime and anonymous-object path

Use `WPHooksRuntimeRegistry` when the object already exists or cannot be discovered from files. `registerHooksOn()` scans attributes once per object and registers supported hooks immediately.

- With a `RuntimeWPHookProvider` injected, attribute closures (hook name, `registerIf`, `executeIf`) are resolved: parameters inject by name from hook args, then from the container, then defaults. Without one, closures are invoked with no arguments — only zero-parameter or defaulted closures work.
- Attribute-argument closures must be static closures (PHP 8.1 constant-expression rule); private members resolve via `self::`, no instance binding is needed.
- Plain `deferRegister` is still ignored on the attribute path (nothing would activate it); `deferRegisterUntilHook` IS supported — the entry sits in the deferred pool and auto-activates when the trigger fires (`deferUntilTriggerHook`, `did_action` short-circuit or `PHP_INT_MIN` listener). There is no manual activation API on the runtime path.
- Static members are silently skipped.
- Hooks are instance-lifetime scoped: the registry keeps a `WeakReference` to the owner; when the owner is garbage-collected the handler nukes itself (`consumeLifetime`) from the pool and `wp_filter`.
- Manual `registerAction()` / `registerFilter()` support closures, captured state, explicit owners, `executeIf`, `once`, and `deferRegisterUntilHook`; `HookRuntimeResolver::resolveOwner()` infers the owner from array callables, bound closures, or invokable objects.
- `AnonClassHookPropertyAbstract` stores a parent class/property pair so the container target resolver can identify an anonymous property value without a call-stack search.

## 6. Teardown

Use `unregisterByHook()`, `unregisterByClass()`, `unregisterByNamespace()`, `unregisterByCallable()`, `unregisterByTags()`, or the wildcard `unregisterByHookPattern()` / `unregisterByTagPattern()` for active container handlers. Use the matching `unregisterDeferredBy*` methods for the deferred pool. `unregisterHooksOn($object)` removes all runtime records for one owner (including its deferred pool entries) and clears its `WeakMap` state.

## Constraints to remember

- Inherited members and static members are intentionally ignored by the shared scanner.
- `registerIf` is registration lifecycle state; `executeIf` is fire-time state.
- Named handler identity matters for reliable `remove_action()` / `remove_filter()` calls.
- Dynamic callable plans need container-resolvable class parameters or defaults; unsupported defaults fall back to reflection.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/WPHooksScanner.php` — discovery, memoization, and cache export.
- `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` — callable plans, gates, dynamic names, and tag resolution (container path).
- `server/Core/Container/Support/WPHooks/Provider/RuntimeWPHookProvider.php` — same resolution on the runtime path.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — active/deferred lifecycle and selectors.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksRuntimeRegistry.php` — immediate object and manual registration.
- `server/Core/Container/Support/WPHooks/Invoker/ContainerLazyHookInvoker.php` — lazy execution and filter fallback behavior.
- `server/Core/Container/Support/WPHooks/Invoker/RuntimeHookInvoker.php` — runtime execution, once, and GC cleanup.
