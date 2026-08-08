<?php

declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\WPHooks\Provider;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;

/**
 * Runtime hook provider — resolves dependencies for attribute-parameter
 * closures (hook name, registerIf, executeIf) on the runtime registry path.
 *
 * The provider is allowed to resolve dependencies (optionally from the
 * container when one is available); the runtime registry remains responsible
 * for runtime ownership and lifetime. When no container is provided, closures
 * without parameters (or with defaulted parameters) still work; typed
 * parameters become unresolvable and the caller decides how to handle that
 * (warn + skip).
 *
 * Attribute-argument closures are static and already scoped to the declaring
 * class (PHP 8.1 RFC 'Closures in constant expressions'), so private members
 * resolve via self:: and no instance binding is needed.
 */
class RuntimeWPHookProvider
{
    use HookProviderTrait;

    public function __construct(
        private readonly ?ContainerInterface $container = null,
    ) {
        // The trait's own constructor is overridden here (container param), so
        // the bound-closure cache must be initialized explicitly — a WeakMap
        // typed property cannot be auto-initialized.
        $this->boundClosureCache = new \WeakMap();
    }

    /**
     * Resolve the hook name, optionally supplying dependencies to the closure.
     *
     * @param string|\Closure $hook       Static hook name or closure resolving to one.
     * @param array<int, array{name: string, type: class-string|null, hasDefault: bool, default: mixed}> $hookParams Callable plan params.
     * @param string          $label      Descriptive label for error messages.
     *
     * @return string The resolved hook name.
     */
    public function resolveRuntimeHookName(string|\Closure $hook, array $hookParams, string $label): string
    {
        return $this->resolveHookName($hook, $this->container, $hookParams, $label);
    }

    /**
     * Evaluate a registerIf gate, optionally supplying dependencies to the closure.
     *
     * @param \Closure|null $registerIf   Gate closure (null = no gate).
     * @param array<int, array{name: string, type: class-string|null, hasDefault: bool, default: mixed}> $params Callable plan params.
     * @param string        $label        Descriptive label for error messages.
     * @param string|null   $targetClass  Class whose scope the closure was declared in.
     */
    public function evaluateRuntimeRegisterIf(?\Closure $registerIf, array $params, string $label, ?string $targetClass = null): bool
    {
        return $this->evaluateRegistrationGate($registerIf, $params, $this->container, $label, $targetClass);
    }

    /**
     * Evaluate an executeIf gate at hook-fire time, optionally supplying
     * dependencies (container or named hook arguments) to the closure.
     *
     * @param \Closure|null $executeIf    Gate closure (null = no gate).
     * @param array<int, array{name: string, type: class-string|null, hasDefault: bool, default: mixed}> $params Callable plan params.
     * @param string        $label        Descriptive label for error messages.
     * @param string|null   $targetClass  Class whose scope the closure was declared in.
     * @param array<string, mixed> $hookArgs Named hook arguments (hook parameter name → value).
     */
    public function evaluateRuntimeExecuteIf(?\Closure $executeIf, array $params, string $label, ?string $targetClass = null, array $hookArgs = []): bool
    {
        return $this->evaluateExecuteIf($executeIf, $params, $this->container, $label, $targetClass, $hookArgs);
    }
}
