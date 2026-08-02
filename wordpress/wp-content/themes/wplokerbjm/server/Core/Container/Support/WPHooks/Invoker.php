<?php
declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\WPHooks;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * Shared condition-gate evaluation for lazy hook handlers.
 *
 * Resolves each closure parameter from the DI container by its class type
 * (falling back to the parameter's default value when the container does not
 * know the class), invokes the closure, and enforces a bool result.
 * @phpstan-type ConditionHookParams array{name: string, type: class-string|null, hasDefault: bool, default: mixed}
 * The gate is evaluated BEFORE the hook fires and predates any defer
 * activation — a `false` result skips the hook entirely.
 */
trait EvaluatesHookCondition
{
    /**
     * Evaluate a condition closure, resolving each parameter from the container
     * (by class type) or falling back to its default value.
     *
     * @param \Closure|null $condition Gate closure; receives resolved dependencies, must return bool.
     * @param ConditionHookParams $conditionParams Pre-computed resolution plan (empty → reflection fallback).
     * @param ContainerInterface $container Container used to resolve typed parameters.
     * @param string $label Handler label used in error messages (class::method / class::$prop).
     *
     * @throws \RuntimeException when a parameter cannot be resolved or the result is not bool
     */
    private function evaluateCondition(
        ?\Closure $condition,
        array $conditionParams,
        ContainerInterface $container,
        string $label,
    ): bool {
        if ($condition === null) {
            return true;
        }
        // try to execute directly whether it returns bool
        if ($conditionParams === []) {
            try {
                $allowed = $condition();
                if (is_bool($allowed)) {
                    return $allowed;
                }
            } catch (\ArgumentCountError) {
                // Ignore
            }
        }

        $values = [];

        if ($conditionParams !== []) {
            // Plan-driven resolution — no reflection on the hook-fire hot path.
            foreach ($conditionParams as $param) {
                $values[] = $this->resolveConditionParam($param, $container, $label);
            }
        } else {
            $values = $this->resolveConditionFallback($condition, $container, $label);
        }

        $allowed = ($condition)(...$values);

        if (!is_bool($allowed)) {
            throw new \RuntimeException(
                'Condition for ' . $label . ' must return bool, got ' . get_debug_type($allowed)
            );
        }

        return $allowed;
    }

    /**
     * Resolve a single plan entry to a value, or throw when unresolvable.
     *
     * @param ConditionHookParams $param
     */
    private function resolveConditionParam(array $param, ContainerInterface $container, string $label): mixed
    {
        $type = $param['type'] ?? null;

        // Extract class string and check DI container
        if (is_string($type) && $type !== '' && $container->has($type)) {
            return $container->get($type);
        }

        // Fallback to parameter default value if container doesn't have it
        if (!empty($param['hasDefault'])) {
            return array_key_exists('default', $param) ? $param['default'] : null;
        }

        throw new \RuntimeException(
            sprintf(
                'Cannot resolve parameter $%s for condition closure in %s: '
                . 'Class not in container and no default value set.',
                $param['name'] ?? 'unknown',
                $label
            )
        );
    }

    /**
     * Resolve all parameters for a condition closure via reflection.
     *
     * Scans all parameters of the given closure and attempts to resolve each
     * using the provided DI container (based on parameter type hints). If a
     * parameter's class is present in the container, its instance is injected;
     * otherwise, the parameter's default value is used if available.
     *
     * @param \Closure $condition The closure to inspect and resolve parameters for.
     * @param ContainerInterface $container The DI container for resolving class-typed parameters.
     * @param string $label A label describing the condition (used for error reporting).
     *
     * @return array An array of resolved values corresponding to each parameter of the closure.
     *
     * @throws \RuntimeException If a non-builtin parameter type cannot be resolved
     *         from the container and has no default value.
     */
    private function resolveConditionFallback(\Closure $condition, ContainerInterface $container, string $label): array
    {
        $reflect = new ReflectionFunction($condition);
        $values = [];

        foreach ($reflect->getParameters() as $param) {
            $type = $param->getType();
            $resolved = false;

            // Extract class string and check DI container
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($container->has($className)) {
                    $values[] = $container->get($className);
                    $resolved = true;
                }
            }

            // Fallback to parameter default value if container doesn't have it
            if (!$resolved) {
                if ($param->isDefaultValueAvailable()) {
                    $values[] = $param->getDefaultValue();
                } else {
                    throw new \RuntimeException(
                        sprintf(
                            'Cannot resolve parameter $%s for condition closure in %s: '
                            . 'Class not in container and no default value set.',
                            $param->getName(),
                            $label
                        )
                    );
                }
            }
        }

        return $values;
    }
}

/**
 * Lazy hook handler — invocable object that defers container resolution to hook-fire time.
 *
 * Unlike closures, this is a named class that appears in debugging tools
 * WordPress can match it by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 * @phpstan-import-type ConditionHookParams from EvaluatesHookCondition
 */
final class LazyHookHandler
{
    use EvaluatesHookCondition;

    public readonly string $label;

    /** @var Closure to invoke the method on any instance (for non-public methods) */
    private ?\Closure $invoker = null;

    /**
     * @param ContainerInterface $container
     * @param class-string $class
     * @param string $method
     * @param 'public'|'protected'|'private' $visibility
     * @param 'action'|'filter' $type
     * @param \Closure():bool|null $condition Gate evaluated before the hook fires; must return bool.
     * @param ConditionHookParams $conditionParams Pre-computed DI plan for the condition closure.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $method,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $condition = null,
        private readonly array $conditionParams = [],
    ) {
        $this->label = $this->class . '::' . $this->method;

        if ($this->visibility !== 'public') {
            $methodName = $this->method;

            // Bind inside $class scope so $instance->privateMethod(...) works natively.
            // newThis=null means the closure is scoped but not bound to a specific instance.
            $this->invoker = \Closure::bind(
                static fn(object $instance, mixed ...$args): mixed => $instance->{$methodName}(...$args),
                null,
                $this->class,
            );
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        try {
            if (!$this->evaluateCondition($this->condition, $this->conditionParams, $this->container, $this->label)) {
                // Skip the hook — return original value for filters so pipeline remains intact
                return ($this->type === 'filter' && array_key_exists(0, $args)) ? $args[0] : null;
            }

            $instance = $this->container->get($this->class);

            $execute = $this->visibility === 'public'
                ? $instance->{$this->method}(...$args)
                : ($this->invoker)($instance, ...$args);
            SharedUtils::isDevelopment() && Logger::debug("LazyHookHandler", "Hook invoke {$this->label}");
            return $execute;
        } catch (\Throwable $e) {
            Logger::error('WPHooksRegistry', 'Error invoking hook for class ' . $this->class . ' and method ' . $this->method . ': ' . $e->getMessage());
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
        }
    }
}

/**
 * Lazy hook handler for property closures.
 *
 * Reads the closure from a property at hook-fire time, using a pre-bound
 * Closure when the property is protected or private.
 *
 * Unlike raw closures, this is a named class that appears in debugging
 * tools and WordPress can match it by instance identity (spl_object_hash)
 * for remove_action()/remove_filter().
 * @phpstan-import-type ConditionHookParams from EvaluatesHookCondition
 */
final class LazyPropertyHookHandler
{
    use EvaluatesHookCondition;

    public readonly string $label;

    /** @var \Closure(object):mixed|null Closure to read the property on any instance (for non-public props) */
    private ?\Closure $reader = null;

    /**
     * @param ContainerInterface $container
     * @param class-string $class
     * @param string $property
     * @param 'public'|'protected'|'private' $visibility
     * @param 'action'|'filter' $type
     * @param \Closure():bool|null $condition Gate evaluated before the hook fires; must return bool.
     * @param ConditionHookParams $conditionParams Pre-computed DI plan for the condition closure.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $property,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $condition = null,
        private readonly array $conditionParams = [],
    ) {
        $this->label = $this->class . '::$' . $this->property;

        if ($this->visibility !== 'public') {
            $propertyName = $this->property;

            // Bind inside $class scope so $instance->privateProp works natively.
            $this->reader = \Closure::bind(
                static fn(object $instance): mixed => $instance->{$propertyName},
                null,
                $this->class,
            );
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        try {
            if (!$this->evaluateCondition($this->condition, $this->conditionParams, $this->container, $this->label)) {
                // Skip the hook entirely — even for deferred hooks — the gate predates activation.
                return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
            }

            $instance = $this->container->get($this->class);

            $callable = $this->visibility === 'public'
                ? $instance->{$this->property}
                : ($this->reader)($instance);

            $isInvokable = $callable instanceof \Closure || (is_object($callable) && method_exists($callable, '__invoke'));

            if (!$isInvokable || !is_callable($callable)) {
                throw new \RuntimeException(
                    "Property {$this->label} is not a valid callable or invokable object."
                );
            }
            SharedUtils::isDevelopment() && Logger::debug('WPHooksRegistry', "Property hook {$this->label} invoked successfully.");
            return $callable(...$args);
        } catch (\Throwable $e) {
            Logger::error('WPHooksRegistry', "Error invoking property hook {$this->label}: {$e->getMessage()}");
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
        }
    }
}

/**
 * Runtime hook handler — invocable object that holds a direct instance reference.
 *
 * Unlike LazyHookHandler (which resolves the service from the container at
 * hook-fire time), this handler is bound to an already-instantiated object.
 * Designed for use with WPHooksRuntimeRegistry for anonymous class hooks
 * that cannot be discovered by the file-based WPHooksScanner.
 *
 * WordPress can match this by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 */
final class RuntimeInstanceHookHandler
{
    public readonly string $label;

    /** @var \Closure|null Closure to invoke the method on the stored instance (for non-public methods) */
    private ?\Closure $invoker = null;

    /**
     * @param object $instance The object instance to invoke methods on.
     * @param string $method   The method name.
     * @param 'public'|'protected'|'private' $visibility
     * @param 'action'|'filter' $type
     */
    public function __construct(
        private readonly object $instance,
        private readonly string $method,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
    ) {
        $this->label = $this->instance::class . '::' . $this->method;

        if ($this->visibility !== 'public') {
            $methodName = $this->method;

            // Bind inside the instance's class scope so
            // $instance->privateMethod(...) works natively.
            $this->invoker = \Closure::bind(
                static fn(object $target, mixed ...$args): mixed => $target->{$methodName}(...$args),
                null,
                $this->instance::class,
            );
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        try {
            $execute = $this->visibility === 'public'
                ? $this->instance->{$this->method}(...$args)
                : ($this->invoker)($this->instance, ...$args);

            return $execute;
        } catch (\Throwable $e) {
            Logger::error(
                'RuntimeInstanceHookHandler',
                'Error invoking hook ' . $this->label . ': ' . $e->getMessage(),
            );
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
        }
    }
}

/**
 * Runtime property hook handler — invocable object that holds a direct instance reference.
 *
 * Reads the property value (a Closure or invokable object) at hook-fire time
 * and invokes it. Designed for use with WPHooksRuntimeRegistry for anonymous
 * class property hooks that cannot be discovered by the file-based scanner.
 *
 * WordPress can match this by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 */
final class RuntimeInstancePropertyHookHandler
{
    public readonly string $label;

    /** @var \Closure(object):mixed|null */
    private ?\Closure $reader = null;

    /**
     * @param object $instance   The object instance whose property holds the callable.
     * @param string $property   The property name.
     * @param 'public'|'protected'|'private' $visibility
     * @param 'action'|'filter' $type
     */
    public function __construct(
        private readonly object $instance,
        private readonly string $property,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
    ) {
        $this->label = $this->instance::class . '::$' . $this->property;

        if ($this->visibility !== 'public') {
            $propertyName = $this->property;

            // Bind inside the instance's class scope so
            // $instance->privateProp works natively.
            $this->reader = \Closure::bind(
                static fn(object $target): mixed => $target->{$propertyName},
                null,
                $this->instance::class,
            );
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        try {
            $callable = $this->visibility === 'public'
                ? $this->instance->{$this->property}
                : ($this->reader)($this->instance);

            if (!\is_callable($callable)) {
                throw new \RuntimeException(
                    "Property {$this->label} is not a valid callable."
                );
            }

            return $callable(...$args);
        } catch (\Throwable $e) {
            Logger::error(
                'RuntimeInstancePropertyHookHandler',
                'Error invoking hook ' . $this->label . ': ' . $e->getMessage(),
            );
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->type === 'filter' && \array_key_exists(0, $args) ? $args[0] : null;
        }
    }
}