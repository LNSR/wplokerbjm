<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

/**
 * Lifecycle phases emitted to `debugLiveUnregistration` closures.
 *
 *  - UnregisterRequested → a removal was requested (before the WP call)
 *  - Unregistered → `remove_action`/`remove_filter` confirmed the removal
 *  - UnregisterFailed → the WP removal failed or the callback was not registered
 *  - OnceConsumed → a once-hook consumed its single fire and detached
 *  - OwnerCollected → the instance-registry owner was garbage-collected
 *  - ProxyReleased → the invoker proxy itself is being destructed
 */
enum LiveUnregistrationPhase: string
{
    case UnregisterRequested = 'unregister_requested';
    case Unregistered = 'unregistered';
    case UnregisterFailed = 'unregister_failed';
    case OnceConsumed = 'once_consumed';
    case OwnerCollected = 'owner_collected';
    case ProxyReleased = 'proxy_released';
}
