<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker\Target;

use Psr\Container\ContainerInterface;

/**
 * Resolves the service from the container and invokes the callable stored in
 * one of its properties (closure or invokable object).
 */
final class ContainerPropertyTarget implements InvocationTarget
{
    /** @var array<class-string, \Closure(object, string):mixed> */
    private static array $readerCache = [];

    private ?\Closure $reader = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $property,
        private readonly string $visibility = 'public',
    ) {
        if ($this->visibility !== 'public') {
            $this->reader = self::$readerCache[$this->class] ??= \Closure::bind(
                static fn(object $instance, string $propertyName): mixed => $instance->{$propertyName},
                null,
                $this->class,
            );
        }
    }

    public function resolveOwner(): ?object
    {
        return $this->container->get($this->class);
    }

    public function invoke(object $owner, array $args): mixed
    {
        $callable = $this->visibility === 'public'
            ? $owner->{$this->property}
            : ($this->reader)($owner, $this->property);

        $isInvokable = $callable instanceof \Closure || (is_object($callable) && method_exists($callable, '__invoke'));

        if (!$isInvokable || !is_callable($callable)) {
            throw new \RuntimeException("Property {$this->class}->{$this->property} is not a valid callable or invokable object.");
        }

        return $callable(...$args);
    }

    public function targetClass(object $owner): object|string
    {
        return $this->class;
    }

    public function ownerRef(): ?\WeakReference
    {
        return null;
    }

    public function isLifetimeScoped(): bool
    {
        return false;
    }
}
