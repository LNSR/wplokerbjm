<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Exceptions;

/**
 * Thrown while scanning instance-registry hooks when an attribute closure
 * declares a non-nullable `self`/`static` parameter.
 *
 * Instance-registry handlers are bound to a live owner through a WeakReference:
 * by the time a deferred gate, lifecycle callback or debug probe runs, the owner
 * may already be gone (or be mid-teardown). A non-nullable `self` would force
 * the resolver to either construct a substitute owner from the container or
 * fatal — both are wrong for a closure that observes the real owner.
 *
 * Declaring the parameter as `?self` is the contract; null means "owner no
 * longer available".
 */
final class NonNullableSelfParameterException extends \InvalidArgumentException
{
    public static function forParameter(string $role, string $label, string $parameter): self
    {
        return new self(\sprintf(
            'Instance-registry closure "%s" for %s declares non-nullable self parameter $%s. '
            . 'Declare it as ?self: the owner may be gone or mid-teardown when this closure runs.',
            $role,
            $label,
            $parameter,
        ));
    }
}
