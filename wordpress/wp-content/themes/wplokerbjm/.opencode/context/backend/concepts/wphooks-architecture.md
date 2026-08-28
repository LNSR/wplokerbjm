# WPHooks Architecture

**Purpose**: Explain how attribute-driven WordPress hooks move from reflection metadata to active or deferred handlers.

## Core idea

WPHooks has two registration paths. The container path scans autoloaded classes and creates lazy handlers; the runtime path scans an already-instantiated object and registers handlers immediately. Both paths wrap callbacks in named invokable objects so WordPress can remove the exact handler instance.

## Container-backed flow

1. `WPHooksScanner` reads classes indexed by the RobotLoader.
2. `HookScannerTrait` scans declared, non-static methods and properties for `#[Action]` / `#[Filter]`.
3. Each attribute becomes a `HookRegistration`; callable metadata is converted into serializable resolution plans.
4. The scanner can export registrations to `WPHooksCache.php` and reload them on later requests.
5. `WPHooksContainerRegistry` resolves the hook name, `registerIf` gate, and tags, then builds a lazy method/property handler.
6. Active handlers call `add_action()` / `add_filter()` during `initialize()`; deferred handlers wait in `DeferredHookManager`.
7. At fire time the handler resolves the service from the container, evaluates `executeIf`, and invokes the method or property callable.

## Runtime flow

`WPHooksRuntimeRegistry::registerHooksOn()` scans an existing object and registers its supported attributes immediately. When a `RuntimeWPHookProvider` is injected, attribute closures (hook name, `registerIf`, `executeIf`) are resolved with optional container / named hook-argument injection; without one, closures must be zero-parameter. Manual `registerAction()` / `registerFilter()` use `RuntimeCallableHookHandler`, letting closures capture runtime state without container resolution.

## Deferred pool (shared)

`DeferredHooksTrait` owns the deferred-handler pool used by both paths:

- `addDeferred()` stores an entry under `[hook][key]`.
- `activateMatchingDeferredEntries()` sweeps the pool, re-evaluates the registration gate via the abstract `gateDeferredActivation()`, and hands matches to an activate callback.
- `unregisterMatchingDeferredEntries()` removes entries without touching active handlers.

`DeferredHookManager` (container path) exposes the micromanage selectors; `WPHooksRuntimeRegistry` consumes the same mechanics behind an automatic-only surface (`deferRegisterUntilHook` only).

## The important boundary

| Concern | Container path | Runtime path |
|---|---|---|
| Target lifetime | Resolved lazily at hook fire | Existing object held via `WeakMap`; handler self-nukes on owner GC |
| Dynamic hook names | Supported through `WPHookPlanProvider` | Supported through `RuntimeWPHookProvider` |
| Conditions | `registerIf` once; `executeIf` per fire | Attribute `registerIf`/`executeIf` resolved via provider |
| Deferred hooks | `deferRegister` + `deferRegisterUntilHook`, many selectors | `deferRegisterUntilHook` auto-activates on trigger |
| Anonymous classes | Use resolver metadata or `AnonClassHookPropertyAbstract` | Direct instance handlers work naturally |

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
- `server/Core/Container/Support/WPHooks/Registry/WPHooksRuntimeRegistry.php` — immediate object/runtime registration path.
- `server/Core/Container/Support/WPHooks/Invoker/ContainerLazyHookInvoker.php` — ContainerLazyHookInvokerTrait, ContainerLazyHookHandler, ContainerLazyPropertyHookHandler.
- `server/Core/Container/Support/WPHooks/Invoker/RuntimeHookInvoker.php` — RuntimeInstanceInvokerTrait, RuntimeInstanceHookHandler, RuntimeInstancePropertyHookHandler, RuntimeCallableHookHandler.
