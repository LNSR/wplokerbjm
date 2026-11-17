<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

/**
 * Container-resolvable dependency for {@see \WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider}
 * coverage.
 *
 * `$constructed` lets the tests prove LAZY resolution: gate/cleanup closures
 * must not instantiate dependencies until they are actually invoked. The
 * counter is reset in each test's setUp().
 */
final class PlanProviderDependency
{
    public static int $constructed = 0;

    public string $marker = 'dep';

    public function __construct()
    {
        self::$constructed++;
    }
}
