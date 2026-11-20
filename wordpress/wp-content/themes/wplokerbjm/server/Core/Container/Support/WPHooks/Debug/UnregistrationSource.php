<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

/**
 * Who initiated an unregistration/lifecycle emission.
 *
 *  - Manual → a direct `unregister()` call on the entry
 *  - Registry → the owning registry's removal path
 *  - Cleaner → the `QueuedHooksCleaner` deferred-removal scheduler
 *  - Destructor → the invoker/entry destructor during teardown
 *  - Once → the once-hook consume path
 *  - Lifetime → the owner instance was garbage-collected
 */
enum UnregistrationSource: string
{
    case Manual = 'manual';
    case Registry = 'registry';
    case Cleaner = 'cleaner';
    case Destructor = 'destructor';
    case Once = 'once';
    case Lifetime = 'lifetime';
}
