# WPHooks API Lookup

**Purpose**: Quickly locate the implementation type or method needed when working with hook registration.

## Core types

| Type | File | Use |
|---|---|---|
| `WPHooksScanner` | `server/Core/Container/Support/WPHooks/WPHooksScanner.php` | `getHookRegistrations()` scans or loads the cache; constructor accepts namespace, cache path, and plan provider. |
| `HookScannerTrait` | `server/Core/Container/Support/WPHooks/Trait/HookScannerTrait.php` | Scans declared non-static methods/properties and reports visibility plus action/filter type. |
| `HookRegistration` | `server/Core/Container/Support/WPHooks/DTO.php` | Immutable metadata DTO; use `fromArray()` and `toArray()` for cache boundaries. |
| `HookKey` | `server/Core/Container/Support/WPHooks/DTO.php` | Canonical identity: class, member, target, type, priority, accepted args; supports class/callable/namespace matching. |
| `WPHookPlanProvider` | `server/Core/Container/Support/WPHooks/WPHookPlanProvider.php` | Builds callable plans; resolves dynamic names, registration gates, execution gates, and dynamic tags. |

## Registries and selectors

| API | Purpose / constraint |
|---|---|
| `WPHooksContainerRegistry::initialize()` | Idempotently resolves and registers active entries; deferred entries stay in `DeferredHookManager`. |
| `activateDeferredByHook/Class/Namespace/Callable/Tags()` | Move matching deferred entries into WordPress; activation re-checks `registerIf`. |
| `unregisterByHook/Class/Namespace/Callable/Tags()` | Remove active entries only; use the `unregisterDeferredBy*` family for deferred entries. |
| `WPHooksRuntimeRegistry::registerHooksOn()` | Scan one existing object once; immediate path skips attribute closure names/conditions. |
| `registerAction()` / `registerFilter()` | Manual runtime path for captured closures and callbacks; pass `owner:` for static/unbound callbacks. |
| `unregisterHooksOn()` | Remove every runtime handler owned by one object. |

`DeferredHookManager` and `HookTargetResolver` are implemented in `Registry/WPHooksContainerRegistry.php`. The resolver accepts array callables, `Class::method` strings, bound first-class callables, invokable objects, property-hook closures, and the anonymous-property metadata object.

## Invokers and utilities

- `ContainerLazyHookHandler` / `ContainerLazyPropertyHookHandler` — resolve services at fire time; support `executeIf` and private/protected access.
- `RuntimeInstanceHookHandler` / `RuntimeInstancePropertyHookHandler` — retain an existing object and invoke its member.
- `RuntimeCallableHookHandler` — wraps manual runtime callbacks and direct `executeIf` conditions.
- `HookTagUtilities::normalizeTags()` / `normalizeTagValue()` — accept strings or string-backed enums and deduplicate tags.
- `HookTags::GRAPHQL_NOCACHE_HEADERS` — current shared tag constant.
- `AnonClassHookPropertyAbstract` — stores `parentClass` and `parentProperty`; filename is `Abstract/AnonClassHookInterface.php`.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — container registry, deferred manager, and target resolver.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksRuntimeRegistry.php` — runtime registry methods.
- `server/Core/Container/Support/WPHooks/Invoker.php` — all five named invoker wrappers.
- `server/Core/Container/Support/WPHooks/Utilities/Tag.php` — tag normalization.
- `server/Core/Container/Support/WPHooks/Constants/Tags.php` — shared tag constants.
- `server/Core/Container/Support/WPHooks/Abstract/AnonClassHookInterface.php` — anonymous property-hook metadata base class.
