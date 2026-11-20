<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker\Target;

/**
 * Invokes a method on a live instance held through a WeakReference.
 */
final class RuntimeMethodTarget implements InvocationTarget
{
    private ?\Closure $invoker = null;
    /** @var array<string, \Closure> */
    private static array $propertyInvokercache = [];

    /**
     * @param \WeakReference<object> $instanceRef
     * @param 'public'|'protected'|'private' $visibility
     */
    public function __construct(
        private readonly \WeakReference $instanceRef,
        private readonly string $method,
        private readonly string $visibility = 'public',
    ) {
        $instance = $this->instanceRef->get();
        if ($this->visibility === 'public' || $instance === null) return;
        $this->invoker = self::$propertyInvokercache[$instance::class] ??= \Closure::bind(
            static fn(object $target, string $methodName, mixed ...$args): mixed => $target->{$methodName}(...$args),
            null,
            $instance,
        );
    }

    public function resolveOwner(): ?object
    {
        return $this->instanceRef->get();
    }

    public function invoke(object $owner, array $args): mixed
    {
        return $this->visibility === 'public'
            ? $owner->{$this->method}(...$args)
            : ($this->invoker)($owner, $this->method, ...$args);
    }

    public function targetClass(object $owner): object|string
    {
        return $owner;
    }

    public function ownerRef(): ?\WeakReference
    {
        return $this->instanceRef;
    }

    public function isLifetimeScoped(): bool
    {
        return true;
    }
}
