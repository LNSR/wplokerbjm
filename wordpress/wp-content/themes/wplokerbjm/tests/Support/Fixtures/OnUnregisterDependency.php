<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

/**
 * Constructor counter used to prove that `onUnregisterDo` parameters are
 * resolved lazily — at invoker release time — and never during registration.
 */
class OnUnregisterDependency
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }
}
