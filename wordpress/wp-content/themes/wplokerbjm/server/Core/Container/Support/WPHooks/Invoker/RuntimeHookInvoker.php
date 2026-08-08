<?php
namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\{RuntimeWPHookProvider};


/**
 * Runtime hook handler — invocable object that holds a direct instance reference.
 *
 * Unlike ContainerLazyHookHandler (which resolves the service from the container at
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
     * @param \Closure|null $executeIf Optional gate: invoked directly, must return bool.
     */
    public function __construct(
        private readonly object $instance,
        private readonly string $method,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $executeIf = null,
        private readonly ?RuntimeWPHookProvider $hookPlanProvider = null,
        private readonly array $executeIfParams = [],
        private readonly array $hookArgNames = [],
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
            if ($this->executeIf !== null) {
                $allowed = $this->hookPlanProvider !== null
                    ? $this->hookPlanProvider->evaluateRuntimeExecuteIf(
                        $this->executeIf,
                        $this->executeIfParams,
                        $this->label,
                        $this->instance::class,
                        $this->buildHookArgs($args),
                    )
                    : ($this->executeIf)();

                if (!is_bool($allowed)) {
                    throw new \RuntimeException(
                        'Condition for ' . $this->label . ' must return bool, got ' . get_debug_type($allowed)
                    );
                }

                if ($allowed === false) {
                    Logger::warning(
                        'RuntimeInstanceHookHandler',
                        'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                    );
                    return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
                }
            }

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

    /**
     * Build named hook arguments for executeIf parameter resolution.
     *
     * @param array<int, mixed> $args Hook arguments received at fire time.
     *
     * @return array<string, mixed> Named args (empty when no names are known).
     */
    private function buildHookArgs(array $args): array
    {
        if ($this->hookArgNames === []) {
            return [];
        }

        return \array_combine(\array_slice($this->hookArgNames, 0, \count($args)), $args) ?: [];
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
     * @param \Closure|null $executeIf Optional gate: invoked directly, must return bool.
     */
    public function __construct(
        private readonly object $instance,
        private readonly string $property,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $executeIf = null,
        private readonly ?RuntimeWPHookProvider $hookPlanProvider = null,
        private readonly array $executeIfParams = [],
        private readonly array $hookArgNames = [],
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
            if ($this->executeIf !== null) {
                $allowed = $this->hookPlanProvider !== null
                    ? $this->hookPlanProvider->evaluateRuntimeExecuteIf(
                        $this->executeIf,
                        $this->executeIfParams,
                        $this->label,
                        $this->instance::class,
                        $this->buildHookArgs($args),
                    )
                    : ($this->executeIf)();

                if (!is_bool($allowed)) {
                    throw new \RuntimeException(
                        'Condition for ' . $this->label . ' must return bool, got ' . get_debug_type($allowed)
                    );
                }

                if ($allowed === false) {
                    Logger::warning(
                        'RuntimeInstancePropertyHookHandler',
                        'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                    );
                    return $this->type === 'filter' && \array_key_exists(0, $args) ? $args[0] : null;
                }
            }

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

    /**
     * Build named hook arguments for executeIf parameter resolution.
     *
     * @param array<int, mixed> $args Hook arguments received at fire time.
     *
     * @return array<string, mixed> Named args (empty when no names are known).
     */
    private function buildHookArgs(array $args): array
    {
        if ($this->hookArgNames === []) {
            return [];
        }

        return \array_combine(\array_slice($this->hookArgNames, 0, \count($args)), $args) ?: [];
    }
}

/**
 * Invocable wrapper for callables registered manually on the runtime registry.
 *
 * Used by WPHooksRuntimeRegistry::registerAction()/registerFilter() so the
 * callback (closure, array-callable, invokable object) can capture the
 * surrounding scope directly — no container resolution involved. An optional
 * condition closure is invoked directly before the callback and must return
 * bool.
 */
final class RuntimeCallableHookHandler
{
    public readonly string $label;

    /**
     * @param callable        $callback  Callable invoked when the hook fires.
     * @param \Closure|null   $executeIf Optional gate: invoked directly, must return bool.
     * @param 'action'|'filter' $type
     */
    public function __construct(
        // `callable` is not a valid property type — validated by the registry before construction.
        private readonly mixed $callback,
        private readonly ?\Closure $executeIf = null,
        private readonly string $type = 'action',
    ) {
        if (is_array($callback)) {
            $this->label = get_debug_type($callback[0]) . '::' . $callback[1];
        } elseif ($callback instanceof \Closure) {
            $this->label = 'closure:' . spl_object_hash($callback);
        } else {
            $this->label = get_debug_type($callback);
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        try {
            if ($this->executeIf !== null) {
                $allowed = ($this->executeIf)();
                if (!is_bool($allowed)) {
                    throw new \RuntimeException(
                        'Condition for ' . $this->label . ' must return bool, got ' . get_debug_type($allowed)
                    );
                }

                if ($allowed === false) {
                    Logger::warning(
                        'RuntimeCallableHookHandler',
                        'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                    );
                    return $this->type === 'filter' && \array_key_exists(0, $args) ? $args[0] : null;
                }
            }

            $callback = $this->callback;

            return $callback(...$args);
        } catch (\Throwable $e) {
            Logger::error(
                'RuntimeCallableHookHandler',
                'Error invoking hook ' . $this->label . ': ' . $e->getMessage(),
            );
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->type === 'filter' && \array_key_exists(0, $args) ? $args[0] : null;
        }
    }
}