<!-- Context: backend/concepts | Priority: high | Version: 1.0 | Updated: 2026-11-18 -->

# WPHooks Architecture

**Purpose**: Explain how attribute-driven WordPress hooks move from reflection metadata to active or deferred handlers.

## Core idea

WPHooks has two registration paths. The container path scans autoloaded classes and creates lazy handlers; the runtime path scans an already-instantiated object and registers handlers immediately. Both paths wrap callbacks in named invokable objects so WordPress can remove the exact handler instance.

## Container-backed flow

1. `WPHooksScanner` reads classes indexed by the RobotLoader.
2. `HookScannerTrait` scans declared, non-static methods and properties for `#[Action]` / `#[Filter]`.
3. Each attribute becomes a `HookRegistration`; callable metadata is converted into serializable resolution plans.
4. The scanner can export registrations to `WPhooksRegistryContainerCache.php` and reload them on later requests.
5. `WPHooksContainerRegistry` resolves the hook name, `registerIf` gate, and tags, then builds a lazy method/property handler.
6. Active handlers call `add_action()` / `add_filter()` during `initialize()`; deferred handlers wait in `ContainerRegistryDeferredHookManager`.
7. At fire time the handler resolves the service from the container, evaluates `executeIf`, and invokes the method or property callable.

## Runtime flow

`WPHooksInstanceRegistry::registerHooksOn()` scans an existing object and registers its supported attributes immediately. Attribute closures (hook name, `registerIf`, `executeIf`) always resolve through the container-backed `WPHookPlanProvider` (named hook-argument injection first, then the container, then defaults). Plain `deferRegister` holds the entry in the deferred pool until a live-instance selector activates it; `deferRegisterUntilHook` auto-activates when the trigger fires (with gate-retry on each fire).

## Deferred pool (shared)

`DeferredHooksTrait` owns the deferred-handler pool used by both paths:

- `addDeferred()` stores an entry under `[hook][key]`.
- `activateMatchingDeferredEntries()` sweeps the pool, re-evaluates the registration gate via the abstract `gateDeferredActivation()`, and hands matches to an activate callback.
- `unregisterMatchingDeferredEntries()` removes entries without touching active handlers.

`ContainerRegistryDeferredHookManager` (container path) exposes the global micromanage selectors. The instance path consumes the same mechanics through `InstanceRegistryDeferredHookManager`, scoped to one live owner: hook (`...ByHookInLiveInstance()`), hook pattern (`...ByHookPatternInLiveInstance()`), callable (`...ByCallableInLiveInstance()`), and owner-wide (`...ByClassInLiveInstance()`) activation/removal. Namespace selectors are intentionally not planned for the instance path; tag and tag-pattern support is the next port.

## The important boundary

| Concern | Container path | Runtime path |
|---|---|---|
| Target lifetime | Resolved lazily at hook fire | Existing object held via `WeakMap`; handler self-nukes on owner GC |
| Dynamic hook names | Supported through `WPHookPlanProvider` | Supported through `WPHookPlanProvider` |
| Conditions | `registerIf` once; `executeIf` per fire | Attribute `registerIf`/`executeIf` resolved via provider |
| Deferred hooks | `deferRegister` + `deferRegisterUntilHook`, many selectors | `deferRegister` + `deferRegisterUntilHook`; owner-scoped hook/hook-pattern/callable/class activate/remove |
| Anonymous classes | Use resolver metadata or `ModuleClassHookMetadata` | Direct instance handlers work naturally |

## Minimal shape

```php
final class SearchHooks
{
    #[Filter('posts_search', acceptedArgs: 2)]
    public function search(string $sql, object $query): string
    {
        return $sql;
    }
}
```

The attribute declares intent; the registry owns lifecycle and the invoker owns failure-safe execution. For the basic attribute syntax, see `concepts/hook-system.md` and `examples/adding-a-hook.md`.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/WPHooksScanner.php` — scans classes and writes the PHP cache.
- `server/Core/Container/Support/WPHooks/Trait/HookScannerTrait.php` — shared declared-member scanner.
- `server/Core/Container/Support/WPHooks/Trait/DeferredHooksTrait.php` — shared deferred pool mechanics.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — container registry, deferred manager, and target resolver.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php` — immediate object/runtime registration path.
- `server/Core/Container/Support/WPHooks/Invoker/ContainerLazyHookInvoker.php` — ContainerLazyHookInvokerTrait, ContainerLazyHookInvoker, ContainerLazyPropertyHookInvoker.
- `server/Core/Container/Support/WPHooks/Invoker/RuntimeHookInvoker.php` — RuntimeInstanceInvokerTrait, RuntimeInstanceHookInvoker, RuntimeInstancePropertyHookInvoker.
