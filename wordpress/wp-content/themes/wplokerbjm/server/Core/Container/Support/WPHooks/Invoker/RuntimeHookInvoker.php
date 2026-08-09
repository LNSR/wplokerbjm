<?php
namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\{RuntimeWPHookProvider};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookInvokerTrait;

/**
 * Shared lifecycle + gate evaluation for runtime instance handlers
 * (method-based and property-based hooks).
 *
 * Cross-domain members (once-removal plumbing, named hook-args builder,
 * filter passthrough) come from the shared {@see HookInvokerTrait}; the
 * actual invocation step is domain-specific via {@see invokeOn()}.
 */
trait RuntimeInstanceInvokerTrait
{
    use HookInvokerTrait;

    /**
     * Invoke the actual hook callback on the (alive) owner instance.
     *
     * @param object $instance The resolved owner instance.
     * @param mixed  ...$args  Hook arguments received at fire time.
     */
    abstract protected function invokeOn(object $instance, mixed ...$args): mixed;

    public function __invoke(mixed ...$args): mixed
    {
        try {
            $instance = $this->instanceRef->get();
            if ($instance === null) {
                // Owner instance is gone — nuke the hook (instance-lifetime scoping).
                $this->consumeLifetime();
                return $this->filterPassthrough($args);
            }

            if ($this->once) {
                if ($this->consumed) {
                    return $this->filterPassthrough($args);
                }
                $this->consumed = true;
            }

            if ($this->executeIf !== null) {
                $allowed = $this->hookPlanProvider !== null
                    ? $this->hookPlanProvider->evaluateRuntimeExecuteIf($this->executeIf, $this->executeIfParams, $this->label, $instance::class, $this->buildHookArgs($args))
                    : ($this->executeIf)();
                if (!is_bool($allowed)) {
                    throw new \RuntimeException(
                        'Condition for ' . $this->label . ' must return bool, got ' . get_debug_type($allowed)
                    );
                }

                if ($allowed === false) {
                    Logger::warning(
                        static::class,
                        'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                    );
                    $this->consumeOnce();
                    return $this->filterPassthrough($args);
                }
            }

            $result = $this->invokeOn($instance, ...$args);
            $this->consumeOnce();
            return $result;
        } catch (\Throwable $e) {
            $this->consumeOnce();
            Logger::error(
                static::class,
                'Error invoking hook ' . $this->label . ': ' . $e->getMessage(),
            );
            return $this->filterPassthrough($args);
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
                static::class,
                'Error removing lifetime hook ' . $this->label . ': ' . $e->getMessage(),
            );
        }
    }
}


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
    use RuntimeInstanceInvokerTrait;

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


    protected function invokeOn(object $instance, mixed ...$args): mixed
    {
        return $this->visibility === 'public'
            ? $instance->{$this->method}(...$args)
            : ($this->invoker)($instance, ...$args);
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
    use RuntimeInstanceInvokerTrait;

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
     * Invoke the property's callable with the given arguments.
     */
    protected function invokeOn(object $instance, mixed ...$args): mixed
    {
        $callable = $this->visibility === 'public'
            ? $instance->{$this->property}
            : ($this->reader)($instance);

        if (!\is_callable($callable)) {
            throw new \RuntimeException(
                "Property {$this->label} is not a valid callable."
            );
        }

        return $callable(...$args);
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
    use HookInvokerTrait;

    public readonly string $label;

    /**
     * @param callable        $callback  Callable invoked when the hook fires.
     * @param \Closure|null   $executeIf Optional gate: invoked directly, must return bool.
     * @param 'action'|'filter' $type
     * @param bool            $once      When true, the registration removes itself after its first
     *                                   fire where the executeIf gate is evaluated (consume-on-any-evaluation).
     */
    public function __construct(
        // `callable` is not a valid property type — validated by the registry before construction.
        private readonly mixed $callback,
        private readonly ?\Closure $executeIf = null,
        private readonly string $type = 'action',
        private readonly bool $once = false,
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
            if ($this->once) {
                if ($this->consumed) {
                    return $this->filterPassthrough($args);
                }
                $this->consumed = true;
            }

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
                    $this->consumeOnce();
                    return $this->filterPassthrough($args);
                }
            }

            $callback = $this->callback;

            $result = $callback(...$args);

            $this->consumeOnce();

            return $result;
        } catch (\Throwable $e) {
            $this->consumeOnce();
            Logger::error(
                'RuntimeCallableHookHandler',
                'Error invoking hook ' . $this->label . ': ' . $e->getMessage(),
            );
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->filterPassthrough($args);
        }
    }
}