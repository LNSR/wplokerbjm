<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

/**
 * Who initiated a deferred activation attempt.
 *
 * The phase describes the lifecycle state; this source describes the cause, so
 * consumers can distinguish an explicit registry call from the automatic
 * `deferRegisterUntilHook` scheduler.
 *
 *  - Manual             → an `activateDeferredBy*()` / runtime activation API
 *  - Scheduler          → the trigger-hook listener fired
 *  - SchedulerImmediate → the trigger had already fired when the entry was
 *                         queued, so activation ran during registration
 */
enum DeferredActivationSource: string
{
    case Manual = 'manual';
    case Scheduler = 'scheduler';
    case SchedulerImmediate = 'scheduler_immediate';
}
