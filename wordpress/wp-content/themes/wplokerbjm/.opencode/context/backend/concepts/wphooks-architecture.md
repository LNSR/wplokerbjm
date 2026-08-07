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

`WPHooksRuntimeRegistry::registerHooksOn()` scans an existing object and registers its supported attributes immediately. Manual `registerAction()` / `registerFilter()` calls use `RuntimeCallableHookHandler`, allowing closures and callbacks that capture runtime state without container resolution.

## The important boundary

| Concern | Container path | Runtime path |
|---|---|---|
| Target lifetime | Resolved lazily at hook fire | Existing object is retained in a `WeakMap` registry |
| Dynamic hook names | Supported through `WPHookPlanProvider` | Attribute closures are skipped |
| Conditions | `registerIf` once; `executeIf` per fire | Attribute conditions skipped; manual `executeIf` runs directly |
| Deferred hooks | Supported and activatable by several selectors | Not supported; registration is immediate |
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
- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — container, deferred, and target-resolution registries.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksRuntimeRegistry.php` — immediate object/runtime registration path.
- `server/Core/Container/Support/WPHooks/Invoker.php` — named lazy and runtime invoker wrappers.
