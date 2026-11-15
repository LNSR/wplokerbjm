<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

/**
 * Opt-in debug probes and their precomputed plans for one invoker.
 *
 * All fields default to null/empty: an invoker without probes pays only the
 * property read that returns null.
 *
 * @phpstan-import-type CallablePlan from \WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider
 */
final readonly class DebugProbes
{
    /**
     * @param CallablePlan $deferredParams
     * @param CallablePlan $liveParams
     * @param CallablePlan $unregistrationParams
     */
    public function __construct(
        public ?\Closure $deferred = null,
        public array $deferredParams = [],
        public ?\Closure $live = null,
        public array $liveParams = [],
        public ?\Closure $unregistration = null,
        public array $unregistrationParams = [],
    ) {}
}
