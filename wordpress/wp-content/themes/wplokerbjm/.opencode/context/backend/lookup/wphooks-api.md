<!-- Context: backend/lookup | Priority: high | Version: 1.2 | Updated: 2026-11-28 -->

# WPHooks API Lookup

**Purpose**: Quickly locate the implementation type or method needed when working with hook registration.

## Core types

| Type | File | Use |
|---|---|---|
| `WPHooksScanner` | `server/Core/Container/Support/WPHooks/WPHooksScanner.php` | `getHookRegistrations()` scans or loads the cache. Constructor: `(\Closure $containerEntries, WPHookPlanProvider $provider, string $namespace = 'WPLokerBJM', string $cacheLocation = '', HookLoggerInterface $logger = new NullHookLogger(), bool $forceNoCache = false)`. The closure returns RobotLoader-indexed candidates merged with known container entries, then filtered by `container->has()`. |
| `HookScannerTrait` | `server/Core/Container/Support/WPHooks/Trait/HookScannerTrait.php` | Scans declared non-static methods/properties and reports visibility plus action/filter type. |
| `HookRegistration` | `server/Core/Container/Support/WPHooks/Registrations/HookRegistration.php` | Immutable container-registration DTO; hydrates via `fromArray()` and exports through VarExporter `__set_state`. |
| `HookKey` | `server/Core/Container/Support/WPHooks/Registrations/HookKey.php` | Canonical identity: class, member, target, type, priority, accepted args; supports class/callable/namespace matching. |
| `InstanceHookMetadata` | `server/Core/Container/Support/WPHooks/Registrations/InstanceHookMetadata.php` | Runtime hook-site metadata, including raw hook/tag closures and plans; hydrates via `fromArray()` and exports via `__set_state`. |
| `CallableHookParams` | `server/Core/Container/Support/WPHooks/Provider/CallableHookParams.php` | Per-parameter plan DTO; `CallablePlan` is `array{isStatic: bool, params: list<CallableHookParams>}`. |
| `HookLoggerInterface` | `server/Core/Container/Support/WPHooks/Logger/HookLoggerInterface.php` | Swappable WPHooks logger: `debug/info/warning/error($category, $message, $context)` + `flush()`. DI binds `SharedLoggerAdapter`; `NullHookLogger` is the no-op default. |
| `WPHookPlanProvider` | `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` | Backwards-compatible facade over `CallablePlanFactory`, `ArgumentResolver`, `ConditionEvaluator`, and `HookDebugDispatcher`. |
| `ModuleHookMetadata` | `server/Core/Container/Support/WPHooks/Abstract/ModuleHookMetadata.php` | Anonymous/inner owner metadata: `outerClassString`, `identifier`, `ownershipPath`. |

## Lifetime warning

Closure attribute parameters resolve by exact hook-argument name, then the live owner for `self`/`static`, then `ContainerInterface::get($type)`, then defaults. WPHooks never calls `make()` and never owns dependency instances; the upstream container's `get()` decides singleton/transient/scoped behavior. Default `DI\autowire(...)` entries are shared per container. Do not assume a typed closure dependency follows the hook/owner lifetime — pass per-fire state as a hook argument. See `concepts/container-lifetimes.md`.

## Registries and selectors

| API | Purpose / constraint |
|---|---|
| `WPHooksContainerRegistry::initialize()` | Idempotently asks the injected `WPHooksScanner` for registrations, registers active entries, and indexes handlers. It does **not** auto-boot the event bridge; `initEventBridge()` is explicit/standalone/test-only. |
| `activateDeferredByHook/Class/Namespace/Callable/Tags()` | Move matching deferred entries into WordPress; activation re-checks `registerIf`. |
| `activateDeferredByHookPattern($pattern)` / `activateDeferredByTagPattern($patterns)` | Wildcard activation; pattern rules from `HookPattern`. |
| `unregisterByHook/Class/Namespace/Callable/Tags()` | Remove active entries only; use the `unregisterDeferredBy*` family for deferred entries. |
| `unregisterByHookPattern($pattern)` / `unregisterByTagPattern($patterns)` | Wildcard removal of active entries. |
| `unregisterDeferredByHookPattern($pattern)` / `unregisterDeferredByTagPattern($patterns)` | Wildcard removal of deferred entries only. |
| `WPHooksInstanceRegistry::registerHooksOn()` | Scan one existing object once; attribute closures resolve through `WPHookPlanProvider`. |
| `activateDeferredByHookInLiveInstance()` / `unregisterDeferredByHookInLiveInstance()` | Runtime hook-name-scoped deferred activation/removal for one live owner. |
| `activateDeferredByHookPatternInLiveInstance()` / `unregisterDeferredByHookPatternInLiveInstance()` | Runtime hook-pattern-scoped deferred activation/removal for one live owner. |
| `activateDeferredByTagsInLiveInstance()` / `unregisterDeferredByTagsInLiveInstance()` | Runtime tag-scoped deferred activation/removal; tags resolve per instance and OR-match. |
| `activateDeferredByTagPatternInLiveInstance()` / `unregisterDeferredByTagPatternInLiveInstance()` | Runtime tag-pattern-scoped deferred activation/removal. |
| `activateDeferredByCallableInLiveInstance()` / `unregisterDeferredByCallableInLiveInstance()` | Runtime callable-scoped deferred activation/removal for held `deferRegister` entries. |
| `activateDeferredByClassInLiveInstance()` / `unregisterDeferredByClassInLiveInstance()` | Runtime owner-scoped deferred activation/removal for every member of one live instance. |
| `unregisterByHookInLiveInstance()` / `unregisterByHookPatternInLiveInstance()` | Remove matching active runtime registrations for one owner only. |
| `unregisterByTagsInLiveInstance()` / `unregisterByTagPatternInLiveInstance()` | Remove active runtime registrations carrying matching tags for one owner only. |
| `unregisterByClassInLiveInstance()` | Remove every active runtime registration owned by one live instance. |
| `unregisterByCallableInLiveInstance()` | Remove every active registration of one member on one live instance. |
| `unregisterHooksOn()` | Remove every runtime handler owned by one object (including deferred entries). |

`ContainerRegistryDeferredHookManager` lives in `Registry/DeferredManager/`; `ContainerRegistryHookTargetResolver` and `InstanceRegistryRuntimeHookResolver` live in `Registry/Resolver/`.

## Invokers and utilities

- `HookInvoker` — one unified invoker for container/runtime and method/property paths (`Invoker/HookInvoker.php`), configured by `HookInvokerConfig`.
- `Invoker\Target\InvocationTarget` — strategy interface with `ContainerMethodTarget`, `ContainerPropertyTarget`, `RuntimeMethodTarget`, and `RuntimePropertyTarget`.
- `HookInvokerTrait` — once/removal plumbing, named hook args, filter passthrough, and `dispatchDepth` reentrancy tracking (`Trait/HookInvokerTrait.php`).
- `HookRemovalController` — once-consume and lifetime/proxy removal (`Invoker/HookRemovalController.php`); `HookArgumentBag` shapes named/variadic args.
- `DeferredHooksTrait` — shared deferred mechanics via abstract `deferredGet/Put/Remove/Count/Entries` + `afterDeferredAdded`; container manager uses a flat pool + `EntriesIndexer`, instance manager uses `WeakMap<owner, entries>` (`Trait/DeferredHooksTrait.php`).
- `WPHookPlanProvider` — facade for `buildCallablePlan`, `buildCallablePlanFromReflector`, `extractPropertyCallablePlan`, `resolveHookName`, `evaluateExecuteIf`, `evaluateRegistrationGate`, `resolveTagCallable`, `resolveOnUnregisterDo`, `assertNullableSelfClosures`.
- `InstanceRegistryRuntimeHookResolver` — live-instance target resolution (`resolveLiveCallableTarget`, `matchesCallableTarget`) in `Registry/Resolver/InstanceRegistryRuntimeResolver.php`.
- `QueuedHooksCleaner` — `WeakMap` of pending entries; immediate unregister or queue until the `all` boundary (`Janitor/QueuedHooksCleaner.php`).
- `HookPattern` — wildcard validation/matching (`Utilities/HookPattern.php`); `HookTagUtilities` / `Tag` normalize strings and string-backed enums (`Utilities/Tag.php`).

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — container registry, deferred manager, and target resolver.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php` — runtime registry + owner-scoped selectors.
- `server/Core/Container/Support/WPHooks/Invoker/HookInvoker.php` — unified invoker; `Invoker/HookInvokerConfig.php`, `Invoker/HookRemovalController.php`, `Invoker/HookArgumentBag.php`, `Invoker/Target/*`.
- `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` — façade; `Plan/CallablePlanFactory.php`, `Resolution/ArgumentResolver.php`, `Resolution/ConditionEvaluator.php`, `Debug/HookDebugDispatcher.php`.
- `server/Core/Container/Support/WPHooks/Logger/` — logger contract, shared adapter, null logger.
- `server/Core/Container/Support/WPHooks/Utilities/HookPattern.php` — wildcard patterns; `Utilities/Tag.php` — tag normalization.
- `server/Core/Container/Support/WPHooks/Trait/HookInvokerTrait.php` — invoker plumbing; `Trait/DeferredHooksTrait.php` — deferred storage hooks.
- `server/Core/Container/Support/WPHooks/Abstract/ModuleHookMetadata.php` — anonymous property-hook metadata base class.
