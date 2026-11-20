<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker\Target;

/**
 * The only axis that differed between the four legacy invoker classes:
 * where the callable lives and how it is reached.
 */
interface InvocationTarget
{
    /** Resolve the live owner, or null when it is gone (runtime) / lazily fetched (container). */
    public function resolveOwner(): ?object;

    /** Invoke the target on the provided owner. */
    public function invoke(object $owner, array $args): mixed;

    /** Class-scope argument for gate resolution (object for runtime, class-string for container). */
    public function targetClass(object $owner): object|string;

    /** Weak reference for cleanup resolution, when the target is lifetime-scoped. */
    public function ownerRef(): ?\WeakReference;

    /** Whether a missing owner must trigger lifetime cleanup. */
    public function isLifetimeScoped(): bool;
}
