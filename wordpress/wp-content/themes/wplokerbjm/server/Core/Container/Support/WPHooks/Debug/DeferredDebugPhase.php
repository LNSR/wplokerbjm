<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

/**
 * Lifecycle phases emitted to `debugDeferredRegistration` closures.
 *
 * Every phase maps to a transition in the shared deferred pool:
 *  - Queued             → `DeferredHooksTrait::addDeferred()`
 *  - ActivationAttempt  → `activateMatchingDeferredEntries()` (before the gate)
 *  - ActivationRejected → `registerIf` returned false (entry stays parked)
 *  - Activated          → the handler was registered with WordPress
 *  - ActivationFailed   → activation callback returned false / threw
 *  - Released           → removed from the pool before activation
 */
enum DeferredDebugPhase: string
{
    case Queued = 'queued';
    case ActivationAttempt = 'activation_attempt';
    case ActivationRejected = 'activation_rejected';
    case Activated = 'activated';
    case ActivationFailed = 'activation_failed';
    case Released = 'released';
}
