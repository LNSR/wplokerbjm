<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

/**
 * Container-registry fixture for `onUnregisterDo` tests.
 *
 * Registrations are supplied as raw arrays, so the class only needs real
 * methods for the lazy invokers to resolve.
 */
class OnUnregisterService
{
    public int $runs = 0;

    public function onHook(): void
    {
        $this->runs++;
    }

    public function otherHook(): void
    {
        $this->runs++;
    }
}
