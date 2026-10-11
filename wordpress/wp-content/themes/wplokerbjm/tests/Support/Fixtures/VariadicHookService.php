<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

/**
 * Fixture for variadic hook-argument forwarding (option A).
 *
 * Mirrors the production shapes in
 * server/Core/Container/Test.php (pure `...$args` handler) and
 * server/Services/GraphQL/Hooks/Search/SearchJobHooks.php (named handler).
 */
final class VariadicHookService
{
    public static array $captured = [];

    public static function reset(): void
    {
        self::$captured = [];
    }

    /** Pure variadic handler — receives the whole argument list as one bucket. */
    public function collectAll(...$args): void
    {
        self::$captured[] = $args;
    }

    /** Named head + variadic tail handler. */
    public function collectTail(mixed $source, ...$args): void
    {
        self::$captured[] = [$source, $args];
    }

    /** Fully named handler (regression shape). */
    public function collectNamed(string $value): void
    {
        self::$captured[] = $value;
    }
}