<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

/**
 * Constructor counter used by the runtime-registry `onUnregisterDo` lazy
 * resolution test.
 *
 * Kept separate from {@see OnUnregisterDependency} so the shared proxy
 * container cannot serve an instance cached by another test class — the
 * counter must start at zero for the "not resolved during registration"
 * assertion to mean anything.
 */
final class OnUnregisterLazyDependency
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }
}
