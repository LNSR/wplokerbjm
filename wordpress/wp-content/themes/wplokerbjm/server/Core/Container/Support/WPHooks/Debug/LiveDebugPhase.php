<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

/**
 * Lifecycle phases emitted to `debugLiveRegistration` closures.
 *
 *  - Registered → handler entered `$wp_filter`
 *  - Dispatching → the invoker body started
 *  - Completed → handler returned normally
 *  - Failed → handler threw (the invoker already applied its fallback)
 *  - Skipped → `executeIf` rejected the fire (filter passes through)
 */
enum LiveDebugPhase: string
{
    case Registered = 'registered';
    case Dispatching = 'dispatching';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
