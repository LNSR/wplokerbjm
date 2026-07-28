<?php
declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\WPHooks;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
/**
 * Lazy hook handler — invocable object that defers container resolution to hook-fire time.
 *
 * Unlike closures, this is a named class that appears in debugging tools
 * WordPress can match it by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 */
final class LazyHookHandler {
    public readonly string $label;

    /** @var Closure to invoke the method on any instance (for non-public methods) */
    private ?\Closure $invoker = null;

    /**
     * @param ContainerInterface $container
     * @param class-string $class
     * @param string $method
     * @param 'public'|'protected'|'private' $visibility
     * @param 'action'|'filter' $type
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $method,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
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
 */
final class LazyPropertyHookHandler
{
    public readonly string $label;

    /** @var \Closure(object):mixed|null Closure to read the property on any instance (for non-public props) */
    private ?\Closure $reader = null;

    /**
     * @param ContainerInterface $container
     * @param class-string $class
     * @param string $property
     * @param 'public'|'protected'|'private' $visibility
     * @param 'action'|'filter' $type
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $property,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
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