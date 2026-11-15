<!-- Context: backend/lookup | Priority: high | Version: 1.0 | Updated: 2026-11-18 -->

# WPHooks API Lookup

**Purpose**: Quickly locate the implementation type or method needed when working with hook registration.

## Core types

| Type | File | Use |
|---|---|---|
| `WPHooksScanner` | `server/Core/Container/Support/WPHooks/WPHooksScanner.php` | `getHookRegistrations()` scans or loads the cache; constructor `(array $compiledContainerMap, WPHookPlanProvider $provider, string $namespace, string $cacheLocation)`. |
| `HookScannerTrait` | `server/Core/Container/Support/WPHooks/Trait/HookScannerTrait.php` | Scans declared non-static methods/properties and reports visibility plus action/filter type. |
| `HookRegistration` | `server/Core/Container/Support/WPHooks/Registrations/HookRegistration.php` | Immutable metadata DTO; hydrates via `fromArray()` and exports through VarExporter `__set_state`. |
| `HookKey` | `server/Core/Container/Support/WPHooks/Registrations/HookKey.php` | Canonical identity: class, member, target, type, priority, accepted args; supports class/callable/namespace matching. |
| `InstanceHookMetadata` | `server/Core/Container/Support/WPHooks/Registrations/InstanceHookMetadata.php` | Runtime hook-site metadata, including static tags and the tag callable/plan; hydrates via `fromArray()` and exports via `__set_state`. |
| `CallableHookParams` | `server/Core/Container/Support/WPHooks/Provider/CallableHookParams.php` | Per-parameter plan DTO; extends `AbstractDataObject`, so VarExporter emits `__set_state`. |
| `WPHookPlanProvider` | `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` | Builds callable plans; resolves dynamic names, registration gates, execution gates, and dynamic tags. Single provider for BOTH container and runtime paths (non-nullable `ContainerInterface`). |

## Registries and selectors

| API | Purpose / constraint |
|---|---|
| `WPHooksContainerRegistry::initialize()` | Idempotently resolves and registers active entries; deferred entries stay in `ContainerRegistryDeferredHookManager`. |
| `activateDeferredByHook/Class/Namespace/Callable/Tags()` | Move matching deferred entries into WordPress; activation re-checks `registerIf`. |
| `activateDeferredByHookPattern($pattern)` / `activateDeferredByTagPattern($patterns)` | Wildcard activation; pattern rules from `HookPattern`. |
| `unregisterByHook/Class/Namespace/Callable/Tags()` | Remove active entries only; use the `unregisterDeferredBy*` family for deferred entries. |
| `unregisterByHookPattern($pattern)` / `unregisterByTagPattern($patterns)` | Wildcard removal of active entries. |
| `unregisterDeferredByHookPattern($pattern)` / `unregisterDeferredByTagPattern($patterns)` | Wildcard removal of deferred entries only. |
| `WPHooksInstanceRegistry::registerHooksOn()` | Scan one existing object once; attribute closures resolve through `WPHookPlanProvider`. |
| `activateDeferredByHookInLiveInstance()` / `unregisterDeferredByHookInLiveInstance()` | Runtime hook-name-scoped deferred activation/removal for one live owner. |
| `activateDeferredByHookPatternInLiveInstance()` / `unregisterDeferredByHookPatternInLiveInstance()` | Runtime hook-pattern-scoped deferred activation/removal for one live owner; pattern rules from `HookPattern`. |
| `activateDeferredByTagsInLiveInstance()` / `unregisterDeferredByTagsInLiveInstance()` | Runtime tag-scoped deferred activation/removal for one live owner; tags are resolved per instance and OR-matched. |
| `activateDeferredByTagPatternInLiveInstance()` / `unregisterDeferredByTagPatternInLiveInstance()` | Runtime tag-pattern-scoped deferred activation/removal for one live owner. |
| `activateDeferredByCallableInLiveInstance()` / `unregisterDeferredByCallableInLiveInstance()` | Runtime callable-scoped deferred activation/removal for held `deferRegister` entries. |
| `activateDeferredByClassInLiveInstance()` / `unregisterDeferredByClassInLiveInstance()` | Runtime owner-scoped deferred activation/removal for every member of one live instance. |
| `unregisterByHookInLiveInstance()` / `unregisterByHookPatternInLiveInstance()` | Remove matching active runtime registrations for one owner only; deferred entries are untouched. |
| `unregisterByTagsInLiveInstance()` / `unregisterByTagPatternInLiveInstance()` | Remove active runtime registrations carrying matching tags for one owner only; deferred entries are untouched. |
| `unregisterByClassInLiveInstance()` | Remove every active runtime registration owned by one live instance; its deferred entries are untouched. |
| `unregisterByCallableInLiveInstance()` | Remove every active registration of one member on one live instance. |
| `unregisterHooksOn()` | Remove every runtime handler owned by one object (incl. its deferred entries). |

`ContainerRegistryDeferredHookManager` lives in `Registry/DeferredManager/`; `ContainerRegistryHookTargetResolver` in `Registry/Resolver/`. The resolver accepts array callables, `Class::method` strings, bound first-class callables, invokable objects, property-hook closures, and the anonymous-property metadata object.

## Invokers and utilities

- `ContainerLazyHookInvoker` / `ContainerLazyPropertyHookInvoker` — resolve services at fire time; support `executeIf` and private/protected access (`Invoker/ContainerLazyHookInvoker.php`).
- `RuntimeInstanceHookInvoker` / `RuntimeInstancePropertyHookInvoker` — retain an existing object and invoke its member; `WeakReference` owner with GC self-cleanup (`Invoker/RuntimeHookInvoker.php`).
- `HookInvokerTrait` — shared once/removal plumbing (`setCleanupCallback`, `consumed`/`removed` guards), `buildHookArgs` named args, `filterPassthrough` (`Trait/HookInvokerTrait.php`).
- `RuntimeInstanceInvokerTrait` — runtime `__invoke` pipeline + `consumeLifetimeAndClean()` GC nuke (`Invoker/RuntimeHookInvoker.php`).
- `DeferredHooksTrait` — shared deferred pool: `addDeferred`, `activateMatchingDeferredEntries`, `unregisterMatchingDeferredEntries`, abstract `gateDeferredActivation` (`Trait/DeferredHooksTrait.php`).
- `WPHookPlanProvider` — plan core + container-backed closure resolution for both paths: `buildCallablePlan`, `resolveHookName`, `evaluateExecuteIf`, `evaluateRegistrationGate`, `resolveTagCallable`, `callableParamNames` (`Provider/WPHookPlanProvider.php`).
- `InstanceRegistryRuntimeHookResolver` — live-instance target resolution (`resolveLiveCallableTarget`, `matchesCallableTarget`), method param names (`resolveHookArgNames`) — in `Registry/WPHooksInstanceRegistry.php`.
- `HookPattern` — wildcard validation/matching: exactly one trailing asterisk, literal prefix ≥ 2 chars, else `InvalidArgumentException`; `matches` / `matchesAny` (`Utilities/HookPattern.php`).
- `HookTagUtilities::normalizeTags()` / `normalizeTagValue()` — accept strings or string-backed enums and deduplicate tags.
- Tags are strings or backed enums; `HookTagUtilities` normalizes and deduplicates them (`Utilities/Tag.php`).
- `ModuleClassHookMetadata` — stores `parentClass` and `parentProperty`; filename is `Abstract/ModuleClassHookMetadata.php`.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — container registry, deferred manager, and target resolver.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php` — runtime registry + `InstanceRegistryRuntimeHookResolver`.
- `server/Core/Container/Support/WPHooks/Invoker/RuntimeHookInvoker.php` — runtime handlers + `RuntimeInstanceInvokerTrait`.
- `server/Core/Container/Support/WPHooks/Invoker/ContainerLazyHookInvoker.php` — lazy handlers + `ContainerLazyHookInvokerTrait`.
- `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` — closure resolution (both paths).
- `server/Core/Container/Support/WPHooks/Utilities/HookPattern.php` — wildcard patterns.
- `server/Core/Container/Support/WPHooks/Utilities/Tag.php` — tag normalization.
- `server/Core/Container/Support/WPHooks/Trait/HookInvokerTrait.php` — invoker plumbing; `Trait/DeferredHooksTrait.php` — deferred pool.
- `server/Core/Container/Support/WPHooks/Abstract/ModuleClassHookMetadata.php` — anonymous property-hook metadata base class.
