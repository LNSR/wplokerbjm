<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

/**
 * Static probe for runtime `onUnregisterDo` coverage.
 *
 * Attribute arguments must be constant expressions, so the cleanup closures
 * declared inside `#[Action]` / `#[Filter]` cannot capture local variables.
 * They report through this probe instead, which also lets each test assert
 * exactly WHICH registration released its cleanup.
 */
final class OnUnregisterProbe
{
    /** @var array<string, int> */
    public static array $calls = [];

    public static ?OnUnregisterDependency $dependency = null;

    public static function reset(): void
    {
        self::$calls = [];
        self::$dependency = null;
    }

    public static function hit(string $name): void
    {
        self::$calls[$name] = (self::$calls[$name] ?? 0) + 1;
    }

    public static function count(string $name): int
    {
        return self::$calls[$name] ?? 0;
    }
}
