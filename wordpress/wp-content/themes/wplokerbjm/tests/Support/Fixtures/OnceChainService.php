<?php
declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

/**
 * Mirrors the production `WPLokerBJM\Core\Wordpress\Plugins\Test` fixture:
 * ten `once: true` action handlers bound to the same hook across the
 * consecutive priorities 0..9.
 *
 * It exists to reproduce the real WordPress dispatch behaviour where a
 * once-hook removing itself mid-dispatch used to make WP_Hook skip the
 * immediately-following priority. Every method must
 * therefore be invoked exactly once when the hook fires, regardless of the
 * removals queued by the handlers dispatched before it.
 *
 * The methods are `public` (instead of the production `private`) so the test
 * can register them through the public registration shape without having to
 * declare an explicit visibility.
 */
final class OnceChainService
{
    /** @var list<int> */
    public static array $fired = [];

    public static function reset(): void
    {
        self::$fired = [];
    }

    public function p0(): void
    {
        self::$fired[] = 0;
    }

    public function p1(): void
    {
        self::$fired[] = 1;
    }

    public function p2(): void
    {
        self::$fired[] = 2;
    }

    public function p3(): void
    {
        self::$fired[] = 3;
    }

    public function p4(): void
    {
        self::$fired[] = 4;
    }

    public function p5(): void
    {
        self::$fired[] = 5;
    }

    public function p6(): void
    {
        self::$fired[] = 6;
    }

    public function p7(): void
    {
        self::$fired[] = 7;
    }

    public function p8(): void
    {
        self::$fired[] = 8;
    }

    public function p9(): void
    {
        self::$fired[] = 9;
    }
}
