<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

use WPLokerBJM\Core\Container\Attributes\Action;

/**
 * Instance-registry fixture for
 * {@see \WPLokerBJM\Tests\WPHookTests\UnregisterByCallableInLiveInstanceTest}.
 *
 * Covers the three target shapes the API has to match:
 * - a method registered at several priorities (repeatable attribute),
 * - a sibling method that must stay untouched,
 * - a property hook and a deferred attribute.
 */
final class OnUnregisterLiveService
{
    public int $alphaRuns = 0;

    public int $betaRuns = 0;

    #[Action('live_alpha', 10)]
    #[Action('live_alpha', 20)]
    public function onAlpha(): void
    {
        $this->alphaRuns++;
    }

    #[Action('live_beta', 10)]
    public function onBeta(): void
    {
        $this->betaRuns++;
    }

    #[Action('live_deferred', deferRegisterUntilHook: 'live_trigger')]
    public function onDeferred(): void
    {
        $this->alphaRuns++;
    }

    #[Action('live_prop', 10)]
    public ?\Closure $propTarget {
        get => $this->propTarget ??= function () {
            $this->alphaRuns++;
        };
    }
}
