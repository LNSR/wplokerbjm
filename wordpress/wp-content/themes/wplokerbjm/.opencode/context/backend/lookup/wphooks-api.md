# WPHooks API Lookup

**Purpose**: Quickly locate the implementation type or method needed when working with hook registration.

## Core types

| Type | File | Use |
|---|---|---|
| `WPHooksScanner` | `server/Core/Container/Support/WPHooks/WPHooksScanner.php` | `getHookRegistrations()` scans or loads the cache; constructor accepts namespace, cache path, and plan provider. |
| `HookScannerTrait` | `server/Core/Container/Support/WPHooks/Trait/HookScannerTrait.php` | Scans declared non-static methods/properties and reports visibility plus action/filter type. |
| `HookRegistration` | `server/Core/Container/Support/WPHooks/DTO.php` | Immutable metadata DTO; use `fromArray()` and `toArray()` for cache boundaries. |
| `HookKey` | `server/Core/Container/Support/WPHooks/DTO.php` | Canonical identity: class, member, target, type, priority, accepted args; supports class/callable/namespace matching. |
| `WPHookPlanProvider` | `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` | Builds callable plans; resolves dynamic names, registration gates, execution gates, and dynamic tags (container path). |
| `RuntimeWPHookProvider` | `server/Core/Container/Support/WPHooks/Provider/RuntimeWPHookProvider.php` | Same closure resolution on the runtime path; optional container. Methods: `resolveRuntimeHookName`, `evaluateRuntimeRegisterIf`, `evaluateRuntimeExecuteIf`. |

## Registries and selectors

| API | Purpose / constraint |
|---|---|
| `WPHooksContainerRegistry::initialize()` | Idempotently resolves and registers active entries; deferred entries stay in `DeferredHookManager`. |
| `activateDeferredByHook/Class/Namespace/Callable/Tags()` | Move matching deferred entries into WordPress; activation re-checks `registerIf`. |
| `activateDeferredByHookPattern($pattern)` / `activateDeferredByTagPattern($patterns)` | Wildcard activation; pattern rules from `HookPattern`. |
| `unregisterByHook/Class/Namespace/Callable/Tags()` | Remove active entries only; use the `unregisterDeferredBy*` family for deferred entries. |
| `unregisterByHookPattern($pattern)` / `unregisterByTagPattern($patterns)` | Wildcard removal of active entries. |
| `unregisterDeferredByHookPattern($pattern)` / `unregisterDeferredByTagPattern($patterns)` | Wildcard removal of deferred entries only. |
| `WPHooksRuntimeRegistry::registerHooksOn()` | Scan one existing object once; attribute closures resolve when a provider is injected. |
| `registerAction()` / `registerFilter()` | Manual runtime path for captured closures and callbacks; params: `executeIf`, `once`, `deferRegisterUntilHook`, `owner`. Identical (hook, callback, priority) registrations are deduplicated. |
| `unregisterHooksOn()` | Remove every runtime handler owned by one object (incl. its deferred entries). |

`DeferredHookManager` and `HookTargetResolver` are implemented in `Registry/WPHooksContainerRegistry.php`. The resolver accepts array callables, `Class::method` strings, bound first-class callables, invokable objects, property-hook closures, and the anonymous-property metadata object.

## Invokers and utilities

- `ContainerLazyHookHandler` / `ContainerLazyPropertyHookHandler` — resolve services at fire time; support `executeIf` and private/protected access (`Invoker/ContainerLazyHookInvoker.php`).
- `RuntimeInstanceHookHandler` / `RuntimeInstancePropertyHookHandler` — retain an existing object and invoke its member; `WeakReference` owner with GC self-cleanup (`Invoker/RuntimeHookInvoker.php`).
- `RuntimeCallableHookHandler` — wraps manual runtime callbacks and direct `executeIf` conditions (`Invoker/RuntimeHookInvoker.php`).
- `HookInvokerTrait` — shared once/removal plumbing (`setRemoveCallback`, `consumed`/`removed` guards), `buildHookArgs` named args, `filterPassthrough` (`Trait/HookInvokerTrait.php`).
- `RuntimeInstanceInvokerTrait` — runtime `__invoke` pipeline + `consumeLifetime()` GC nuke (`Invoker/RuntimeHookInvoker.php`).
- `ContainerLazyHookInvokerTrait` — `executeHook()` pipeline; unresolvable gate on a once-hook is treated as pass (`Invoker/ContainerLazyHookInvoker.php`).
- `DeferredHooksTrait` — shared deferred pool: `addDeferred`, `activateMatchingDeferredEntries`, `unregisterMatchingDeferredEntries`, abstract `gateDeferredActivation` (`Trait/DeferredHooksTrait.php`).
- `HookProviderTrait` — shared plan core for both providers: `buildCallablePlan`, `resolveHookName`, `evaluateExecuteIf`, `evaluateRegistrationGate`, `resolveTagCallable`, `callableParamNames` (`Trait/HookProviderTrait.php`).
- `HookRuntimeResolver` — runtime owner inference (`resolveOwner`), closure hook names (`resolveClosureHook`), method param names (`resolveHookArgNames`) — in `Registry/WPHooksRuntimeRegistry.php`.
- `HookPattern` — wildcard validation/matching: exactly one trailing asterisk, literal prefix ≥ 2 chars, else `InvalidArgumentException`; `matches` / `matchesAny` (`Utilities/HookPattern.php`).
- `HookTagUtilities::normalizeTags()` / `normalizeTagValue()` — accept strings or string-backed enums and deduplicate tags.
- `HookTags::GRAPHQL_NOCACHE_HEADERS` — current shared tag constant.
- `AnonClassHookPropertyAbstract` — stores `parentClass` and `parentProperty`; filename is `Abstract/AnonClassHookInterface.php`.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — container registry, deferred manager, and target resolver.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksRuntimeRegistry.php` — runtime registry + `HookRuntimeResolver`.
- `server/Core/Container/Support/WPHooks/Invoker/RuntimeHookInvoker.php` — runtime handlers + `RuntimeInstanceInvokerTrait`.
- `server/Core/Container/Support/WPHooks/Invoker/ContainerLazyHookInvoker.php` — lazy handlers + `ContainerLazyHookInvokerTrait`.
- `server/Core/Container/Support/WPHooks/Provider/RuntimeWPHookProvider.php` — runtime closure resolution.
- `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` — container closure resolution.
- `server/Core/Container/Support/WPHooks/Utilities/HookPattern.php` — wildcard patterns.
- `server/Core/Container/Support/WPHooks/Utilities/Tag.php` — tag normalization.
- `server/Core/Container/Support/WPHooks/Trait/HookProviderTrait.php` — plan core; `Trait/HookInvokerTrait.php` — invoker plumbing; `Trait/DeferredHooksTrait.php` — deferred pool.
- `server/Core/Container/Support/WPHooks/Constants/Tags.php` — shared tag constants.
- `server/Core/Container/Support/WPHooks/Abstract/AnonClassHookInterface.php` — anonymous property-hook metadata base class.
