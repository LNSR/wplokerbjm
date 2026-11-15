<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

/**
 * Named scope target for closure-binding coverage in {@see \WPLokerBJM\Tests\WPHookTests\Provider\WPHookPlanProviderTest}.
 *
 * Tests define closures that reference `self::$secret` from OUTSIDE this
 * class; only a successful `Closure::bind(..., PlanProviderScope::class)`
 * inside the provider makes that reference legal and returns `'hidden'`.
 */
final class PlanProviderScope
{
    private static string $secret = 'hidden';

    public static function staticHandler(PlanProviderDependency $dep, string ...$items): void
    {
    }

    public function instanceHandler(string $value, PlanProviderDependency $dep, int $count = 7): void
    {
    }

    /**
     * Returns a `self`-typed gate closure whose scope is this NAMED class,
     * so the plan records the class-string type instead of the literal `self`.
     */
    public function selfGate(): \Closure
    {
        return static fn (self $self): bool => $self instanceof self;
    }
}
