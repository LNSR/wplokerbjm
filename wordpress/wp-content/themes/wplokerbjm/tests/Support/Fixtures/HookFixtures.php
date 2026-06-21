<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Attributes\Filter;

/**
 * Test fixture: a service with an instance-method action hook.
 *
 * Tracks how many times the constructor runs and the ID of the most recent
 * instance so tests can assert lazy-resolution semantics:
 *  - The constructor must NOT run during `Init::initialize()`.
 *  - It must run exactly once when the hook first fires.
 *  - Subsequent fires must reuse the same instance.
 */
class LazyHookService
{
    public static int $instantiationCount = 0;
    public static array $capturedValues = [];

    public int $id;

    public function __construct()
    {
        self::$instantiationCount++;
        $this->id = self::$instantiationCount;
    }

    #[Action(hook: 'lazy_action_hook', priority: 10, acceptedArgs: 1)]
    public function onAction(string $value = 'default'): void
    {
        self::$capturedValues[] = ['id' => $this->id, 'value' => $value];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public static function reset(): void
    {
        self::$instantiationCount = 0;
        self::$capturedValues = [];
    }
}

/**
 * Test fixture: a service with an instance-method filter hook.
 *
 * Verifies lazy resolution works for filters too, and that variadic arguments
 * are forwarded to the target method.
 */
class FilterService
{
    public static int $instantiationCount = 0;
    public static array $capturedArgs = [];

    public int $id;

    public function __construct()
    {
        self::$instantiationCount++;
        $this->id = self::$instantiationCount;
    }

    #[Filter(hook: 'lazy_filter_hook', priority: 10, acceptedArgs: 2)]
    public function onFilter(string $value, string $extra = ''): string
    {
        self::$capturedArgs[] = ['id' => $this->id, 'value' => $value, 'extra' => $extra];
        return $value . $extra;
    }

    public static function reset(): void
    {
        self::$instantiationCount = 0;
        self::$capturedArgs = [];
    }
}
