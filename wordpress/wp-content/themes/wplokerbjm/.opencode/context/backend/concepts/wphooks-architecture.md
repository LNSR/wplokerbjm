<!-- Context: backend/concepts | Priority: high | Version: 1.1 | Updated: 2026-11-27 -->

# WPHooks Architecture

**Purpose**: Explain how attribute-driven WordPress hooks move from reflection metadata to active or deferred handlers.

## Core idea

WPHooks has two registration paths. The container path scans file-discovered classes and creates lazy handlers; the runtime path scans an already-instantiated object and registers handlers immediately. Both paths share `HookInvoker`, deferred-pool mechanics, the plan provider, and the injected `HookLoggerInterface`.

## Container-backed flow

1. `WPHooksScanner` receives a closure that merges RobotLoader-indexed classes with `$container->getKnownEntryNames()` and filters by `$container->has()`. This is stable file discovery, not a snapshot of services instantiated by the current request.
2. `HookScannerTrait` scans declared, non-static methods and properties for `#[Action]` / `#[Filter]`.
3. Each attribute becomes a `HookRegistration`; callable metadata becomes serializable resolution plans.
4. The scanner can export registrations to `WPhooksRegistryContainerCache.php` and reload them on later requests.
5. `WPHooksContainerRegistry` receives the scanner through DI, resolves hook names, `registerIf` gates, and tags, then builds a `HookInvoker` through the appropriate `Invoker\Target` strategy.
6. Active handlers call `add_action()` / `add_filter()` during `initialize()`; deferred handlers wait in `ContainerRegistryDeferredHookManager`.
7. At fire time the target resolves the service from the container, `HookInvoker` evaluates `executeIf`, and invokes the method or property callable.

## Runtime flow

`WPHooksInstanceRegistry::registerHooksOn()` scans an existing object and registers its supported attributes immediately. Attribute closures (hook name, `registerIf`, `executeIf`) resolve through the container-backed `WPHookPlanProvider` (named hook-argument injection first, then the container, then defaults). Plain `deferRegister` holds the entry in the owner's deferred bucket until a live-instance selector activates it; `deferRegisterUntilHook` auto-activates when its trigger fires.

## Deferred pools

`DeferredHooksTrait` now exposes storage hooks instead of owning one concrete pool:

- `deferredGet/Put/Remove/Count/Entries` — implemented by each manager.
- `afterDeferredAdded()` — container manager uses it to update `EntriesIndexer`.
- `activateMatchingDeferredEntries()` / `unregisterMatchingDeferredEntries()` — shared sweeps, owner-aware for runtime entries.
- `gateDeferredActivation()` remains manager-specific.

`ContainerRegistryDeferredHookManager` uses a flat `array<string, DeferredHookEntryHandler>` plus the container-only `EntriesIndexer`. `InstanceRegistryDeferredHookManager` uses `WeakMap<object, array<string, DeferredHookEntryHandler>>` keyed by owner: sibling instances are isolated, and owner GC drops the bucket and its auto-scheduler immediately.

## The important boundary

| Concern | Container path | Runtime path |
|---|---|---|
| Target lifetime | Resolved lazily at hook fire | Existing object held via `WeakMap`; handler self-nukes on owner GC |
| Dynamic hook names | `WPHookPlanProvider` | `WPHookPlanProvider` |
| Conditions | `registerIf` once; `executeIf` per fire | Attribute `registerIf`/`executeIf` resolved via provider |
| Deferred hooks | Flat pool + many selectors | Owner-keyed `WeakMap` + owner-scoped selectors |
| Cleanup | `QueuedHooksCleaner` + `HookRemovalController` | Same, plus lifetime/owner GC path |
| Anonymous classes | `ModuleHookMetadata` (`outerClassString`, `identifier`) | Direct instance handlers; `ModuleHookMetadata` gives stable cache identity |
| Logging | Injected `HookLoggerInterface` | Injected `HookLoggerInterface` |

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

The attribute declares intent; the registry owns lifecycle and the invoker owns failure-safe execution. For basic syntax, see `concepts/hook-system.md` and `examples/adding-a-hook.md`.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/WPHooksScanner.php` — scans classes and writes the PHP cache.
- `server/Core/Container/Support/WPHooks/Trait/HookScannerTrait.php` — shared declared-member scanner.
- `server/Core/Container/Support/WPHooks/Trait/DeferredHooksTrait.php` — shared deferred storage/sweep mechanics.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksContainerRegistry.php` — container registry.
- `server/Core/Container/Support/WPHooks/Registry/WPHooksInstanceRegistry.php` — runtime/object registration path.
- `server/Core/Container/Support/WPHooks/Registry/DeferredManager/ContainerRegistryDeferredHookManager.php` — flat container pool + indexer.
- `server/Core/Container/Support/WPHooks/Registry/DeferredManager/InstanceRegistryDeferredHookManager.php` — owner-keyed WeakMap pool.
- `server/Core/Container/Support/WPHooks/Invoker/HookInvoker.php` — unified invoker; `Invoker/Target/*` — invocation strategies.
- `server/Core/Container/Support/WPHooks/Logger/` — swappable logger contract/adapters.
