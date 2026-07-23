<?php
namespace WPLokerBJM\Core\Container\Support\WPHooks;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Shared\Log\Logger;
/**
 * Lazy hook handler — invocable object that defers container resolution to hook-fire time.
 *
 * Unlike closures, this is a named class that appears in debugging tools
 * WordPress can match it by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 */
class LazyHookHandler
{
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
                static fn(object $instance, array $args): mixed => $instance->{$methodName}(...$args),
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
                : ($this->invoker)($instance, $args);
            // SharedUtils::isDevelopment() && Logger::debug("LazyHookHandler", "Hook invoke {$this->label}");
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
class LazyPropertyHookHandler
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

            return $callable(...$args);
        } catch (\Throwable $e) {
            Logger::error('WPHooksRegistry', "Error invoking property hook {$this->label}: {$e->getMessage()}");
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
        }
    }
}