<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks;

use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;

/**
 * Builds and resolves DI parameter plans for hook callables.
 *
 * Both the condition gate and the dynamic hook-name closure share one
 * resolution core: plans are pre-computed at scan time (via
 * {@see buildCallablePlan()}), exported to the hooks cache file, and resolved
 * from the container at hook-fire time — so the hot path never needs
 * reflection unless a plan is missing (stale cache / unexportable defaults).
 *
 * The resolution machinery lives in {@see HookProviderTrait} so the runtime
 * provider can share it at the same pace.
 */
class WPHookPlanProvider
{
    use HookProviderTrait;
}
