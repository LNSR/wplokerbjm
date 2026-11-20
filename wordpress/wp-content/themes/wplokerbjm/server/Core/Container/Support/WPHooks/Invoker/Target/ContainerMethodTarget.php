<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker\Target;

use Psr\Container\ContainerInterface;

/**
 * Lazily resolves the service from the container on first dispatch and invokes
 * a method on it.
 */
final class ContainerMethodTarget implements InvocationTarget
{
    /** @var array<class-string, \Closure(object, string, mixed...):mixed> */
    private static array $methodInvokerCache = [];

    private ?\Closure $invoker = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $method,
        private readonly string $visibility = 'public',
    ) {
        if ($this->visibility !== 'public') {
            $this->invoker = self::$methodInvokerCache[$this->class] ??= \Closure::bind(
                static fn(object $instance, string $methodName, mixed ...$args): mixed => $instance->{$methodName}(...$args),
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
        return $this->visibility === 'public'
            ? $owner->{$this->method}(...$args)
            : ($this->invoker)($owner, $this->method, ...$args);
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
