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
    /** @var \Closure|null Callback that nukes this registration once the once-hook is consumed. */
    private ?\Closure $removeCallback = null;

    /** @var bool True once the first fire reached gate evaluation (consume-on-any-evaluation). */
    private bool $consumed = false;

    /** @var bool True once the removal callback fired (idempotency guard). */
    private bool $removed = false;

    /** @var \WeakReference<object> Weak reference to the owner instance — keeps it collectible (instance-lifetime scoping). */
    private readonly \WeakReference $instanceRef;

    public function __construct(
        object $instance,
        private readonly string $method,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $executeIf = null,
        private readonly ?RuntimeWPHookProvider $hookPlanProvider = null,
        private readonly array $executeIfParams = [],
        private readonly array $hookArgNames = [],
        private readonly bool $once = false,
    ) {
        // Keep the owner collectible: only a weak reference is retained, so
        // the instance can be garbage-collected while the hook is registered.
        // On death the hook nukes itself (instance-lifetime scoping).
        $this->instanceRef = \WeakReference::create($instance);

        $this->label = $instance::class . '::' . $this->method;

        if ($this->visibility !== 'public') {
            $methodName = $this->method;

            // Bind inside the instance's class scope so
            // $instance->privateMethod(...) works natively.
            $this->invoker = \Closure::bind(
                static fn(object $target, mixed ...$args): mixed => $target->{$methodName}(...$args),
                null,
                $instance::class,
            );
        }
    }

    /**
     * Register the callback that nukes this registration after a once-hook
     * has been consumed (first fire where the executeIf gate was evaluated).
     */
    public function setRemoveCallback(?\Closure $callback): void
    {
        $this->removeCallback = $callback;
    }

    /**
     * Consume the once-hook: fire the removal callback exactly once.
     */
    private function consumeOnce(): void
    {
        if (!$this->once || $this->removed || $this->removeCallback === null) {
            return;
        }

        $this->removed = true;

        try {
            ($this->removeCallback)();
        } catch (\Throwable $e) {
            Logger::error(
                'RuntimeInstanceHookHandler',
                'Error removing once-hook ' . $this->label . ': ' . $e->getMessage(),
            );
        }
    }

    /**
     * Nuke the registration when its owning instance has been garbage-collected
     * (instance-lifetime scoping). Idempotent — shares the $removed guard with
     * the once flow.
     */
    private function consumeLifetime(): void
    {
        if ($this->removed || $this->removeCallback === null) {
            return;
        }

        $this->removed = true;

        try {
            ($this->removeCallback)();
        } catch (\Throwable $e) {
            Logger::error(
                'RuntimeInstanceHookHandler',
                'Error removing lifetime hook ' . $this->label . ': ' . $e->getMessage(),
            );
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        try {
            $instance = $this->instanceRef->get();
            if ($instance === null) {
                // Owner instance is gone — nuke the hook (instance-lifetime scoping).
                $this->consumeLifetime();
                return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
            }

            if ($this->once) {
                if ($this->consumed) {
                    return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
                }
                $this->consumed = true;
            }

            if ($this->executeIf !== null) {
                $allowed = $this->hookPlanProvider !== null
                    ? $this->hookPlanProvider->evaluateRuntimeExecuteIf(
                        $this->executeIf,
                        $this->executeIfParams,
                        $this->label,
                        $instance::class,
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
                    $this->consumeOnce();
                    return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
                }
            }

            $execute = $this->visibility === 'public'
                ? $instance->{$this->method}(...$args)
                : ($this->invoker)($instance, ...$args);

            $this->consumeOnce();

            return $execute;
        } catch (\Throwable $e) {
            $this->consumeOnce();
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
    /** @var \Closure|null Callback that nukes this registration once the once-hook is consumed. */
    private ?\Closure $removeCallback = null;

    /** @var bool True once the first fire reached gate evaluation (consume-on-any-evaluation). */
    private bool $consumed = false;

    /** @var bool True once the removal callback fired (idempotency guard). */
    private bool $removed = false;

    /** @var \WeakReference<object> Weak reference to the owner instance — keeps it collectible (instance-lifetime scoping). */
    private readonly \WeakReference $instanceRef;

    public function __construct(
        object $instance,
        private readonly string $property,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $executeIf = null,
        private readonly ?RuntimeWPHookProvider $hookPlanProvider = null,
        private readonly array $executeIfParams = [],
        private readonly array $hookArgNames = [],
        private readonly bool $once = false,
    ) {
        // Keep the owner collectible: only a weak reference is retained, so
        // the instance can be garbage-collected while the hook is registered.
        // On death the hook nukes itself (instance-lifetime scoping).
        $this->instanceRef = \WeakReference::create($instance);

        $this->label = $instance::class . '::$' . $this->property;

        if ($this->visibility !== 'public') {
            $propertyName = $this->property;

            // Bind inside the instance's class scope so
            // $instance->privateProp works natively.
            $this->reader = \Closure::bind(
                static fn(object $target): mixed => $target->{$propertyName},
                null,
                $instance::class,
            );
        }
    }

    /**
     * Register the callback that nukes this registration after a once-hook
     * has been consumed (first fire where the executeIf gate was evaluated).
     */
    public function setRemoveCallback(?\Closure $callback): void
    {
        $this->removeCallback = $callback;
    }

    /**
     * Consume the once-hook: fire the removal callback exactly once.
     */
    private function consumeOnce(): void
    {
        if (!$this->once || $this->removed || $this->removeCallback === null) {
            return;
        }

        $this->removed = true;

        try {
            ($this->removeCallback)();
        } catch (\Throwable $e) {
            Logger::error(
                'RuntimeInstancePropertyHookHandler',
                'Error removing once-hook ' . $this->label . ': ' . $e->getMessage(),
            );
        }
    }

    /**
     * Nuke the registration when its owning instance has been garbage-collected
     * (instance-lifetime scoping). Idempotent — shares the $removed guard with
     * the once flow.
     */
    private function consumeLifetime(): void
    {
        if ($this->removed || $this->removeCallback === null) {
            return;
        }

        $this->removed = true;

        try {
            ($this->removeCallback)();
        } catch (\Throwable $e) {
            Logger::error(
                'RuntimeInstancePropertyHookHandler',
                'Error removing lifetime hook ' . $this->label . ': ' . $e->getMessage(),
            );
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        try {
            $instance = $this->instanceRef->get();
            if ($instance === null) {
                // Owner instance is gone — nuke the hook (instance-lifetime scoping).
                $this->consumeLifetime();
                return $this->type === 'filter' && \array_key_exists(0, $args) ? $args[0] : null;
            }

            if ($this->once) {
                if ($this->consumed) {
                    return $this->type === 'filter' && \array_key_exists(0, $args) ? $args[0] : null;
                }
                $this->consumed = true;
            }

            if ($this->executeIf !== null) {
                $allowed = $this->hookPlanProvider !== null
                    ? $this->hookPlanProvider->evaluateRuntimeExecuteIf(
                        $this->executeIf,
                        $this->executeIfParams,
                        $this->label,
                        $instance::class,
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
                    $this->consumeOnce();
                    return $this->type === 'filter' && \array_key_exists(0, $args) ? $args[0] : null;
                }
            }

            $callable = $this->visibility === 'public'
                ? $instance->{$this->property}
                : ($this->reader)($instance);

            if (!\is_callable($callable)) {
                throw new \RuntimeException(
                    "Property {$this->label} is not a valid callable."
                );
            }

            $result = $callable(...$args);

            $this->consumeOnce();

            return $result;
        } catch (\Throwable $e) {
            $this->consumeOnce();
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