<!-- Context: project-intelligence/wphooks-logger | Priority: high | Version: 1.0 | Updated: 2026-11-27 -->

# WPHooks Logger

**Purpose**: The WPHooks subsystem owns a small, swappable logging contract instead of calling the project's static `Shared\Log\Logger` directly. Container DI supplies the concrete backend.

## Contract

```php
interface HookLoggerInterface
{
    public function debug(string $category, string $message, array $context = []): void;
    public function info(string $category, string $message, array $context = []): void;
    public function warning(string $category, string $message, array $context = []): void;
    public function error(string $category, string $message, array $context = []): void;
    public function flush(): void;
}
```

The first argument is the **log category** (usually `__METHOD__` or `static::class`), matching existing WPHooks call sites. It is not a Monolog channel.

## Implementations

| Class | Role |
|---|---|
| `SharedLoggerAdapter` | Default production adapter: delegates every level and `flush()` to `Shared\Log\Logger` |
| `NullHookLogger` | No-op default for standalone construction, CLI tools, and tests |

Swap the backend by replacing the `HookLoggerInterface` binding with another implementation. WPHooks call sites do not change.

## DI Wiring

```php
SharedLoggerAdapter::class => \DI\autowire(SharedLoggerAdapter::class),
HookLoggerInterface::class => \DI\get(SharedLoggerAdapter::class),
```

Every WPHooks definition that logs receives `\DI\get(HookLoggerInterface::class)`. Constructor defaults use `new NullHookLogger()` so direct construction remains possible without a container.

## Injection Surface

- `WPHooksScanner`; `WPHookPlanProvider` forwards to `CallablePlanFactory`, `ConditionEvaluator`, `HookDebugDispatcher`
- `HookInvokerConfig` → `HookInvoker`, `HookRemovalController`, `HookDebugEmitter`; all four static invoker factories accept an optional logger
- `HandlerEntryTrait` consumers: `ContainerRegistryHandlerEntry`, `RuntimeRegistryHandlerEntry`; `DeferredHookEntryHandler` and its factory conversions
- `WPHooksContainerRegistry`, `WPHooksInstanceRegistry`, both deferred managers, `QueuedHooksCleaner`, `WPHooksInstanceObjectCache`
- `HookTargetResolverTrait` consumers: `ContainerRegistryHookTargetResolver`, `InstanceRegistryRuntimeHookResolver`

## Flush Semantics

`flush()` belongs to the injected instance, not a global. `SharedLoggerAdapter::flush()` delegates to `Shared\Log\Logger::flush()`. A replacement backend that buffers must flush its own buffer at shutdown; do not assume the shared static logger flushes it.

## Exclusions

`Abstract/ModuleHookMetadata.php` intentionally remains on `Shared\Log\Logger` until it is decoupled from `AsInnerClass`. `SharedUtils` static dependencies are also untouched; this migration is about the logging boundary, not a wholesale static-dependency purge.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Logger/HookLoggerInterface.php`
- `server/Core/Container/Support/WPHooks/Logger/SharedLoggerAdapter.php`
- `server/Core/Container/Support/WPHooks/Logger/NullHookLogger.php`
- `server/Core/Container/Definitions/Core.php` — interface binding + explicit WPHooks logger arguments
- `tests/Support/InMemoryHookLogger.php` and `tests/WPHookTests/Logger/HookLoggerTest.php` — test double and injection coverage
- `server/Core/Container/Support/WPHooks/Abstract/ModuleHookMetadata.php` — intentional shared-logger exception

## Related Files

- `technical-domain.md` — WPHooks subsystem map and hook attribute surface
- `wphooks-ownership-raii.md` — ownership, deferred pools, cleanup
- `wphooks-registry-events.md` — control-plane event bridge
- `navigation.md` — context index and quick routes
