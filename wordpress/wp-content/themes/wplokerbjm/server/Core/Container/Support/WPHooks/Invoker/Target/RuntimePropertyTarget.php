<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker\Target;

/**
 * Reads and invokes the callable stored in a property of a live instance held
 * through a WeakReference.
 */
final class RuntimePropertyTarget implements InvocationTarget
{
    private ?\Closure $reader = null;
    /** @var array<string, \Closure> */
    private static array $propertyReaderCache = [];

    /**
     * @param \WeakReference<object> $instanceRef
     * @param 'public'|'protected'|'private' $visibility
     * @return void
     */
    public function __construct(
        private readonly \WeakReference $instanceRef,
        private readonly string $property,
        private readonly string $visibility = 'public',
    ) {
        $instance = $this->instanceRef->get();
        if ($this->visibility === 'public' || $instance === null) return;
        $this->reader = self::$propertyReaderCache[$instance::class] ??= \Closure::bind(
            static fn(object $target, string $propertyName): mixed => $target->{$propertyName},
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
        $callable = $this->visibility === 'public'
            ? $owner->{$this->property}
            : ($this->reader)($owner, $this->property);

        if (!\is_callable($callable)) {
            throw new \RuntimeException('Property ' . $owner::class . '->' . $this->property . ' is not a valid callable.');
        }

        return $callable(...$args);
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
