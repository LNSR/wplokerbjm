<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Exceptions;

/**
 * Raised when a debug probe parameter cannot be resolved under the
 * owner-optional (lifecycle emission) contract.
 *
 * The probe emitter catches and logs this exception: a broken probe is a
 * consumer-side contract violation, never a reason to interrupt the hook
 * lifecycle.
 */
final class HookDebugProbeException extends \RuntimeException
{
    public static function nonNullableSelf(string $label, string $parameter): self
    {
        return new self(\sprintf(
            'Debug probe for %s declares non-nullable self parameter $%s but no live owner is available. '
            . 'Declare it as ?self for lifecycle emissions.',
            $label,
            $parameter,
        ));
    }

    public static function unresolved(string $label, string $parameter, ?string $type): self
    {
        return new self(\sprintf(
            'Debug probe for %s cannot resolve parameter $%s (type: %s): not the DTO, not the live owner, '
            . 'not available in the container and no default/null fallback.',
            $label,
            $parameter,
            $type ?? 'mixed',
        ));
    }
}
