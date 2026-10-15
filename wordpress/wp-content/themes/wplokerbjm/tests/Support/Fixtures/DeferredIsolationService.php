<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

use WPLokerBJM\Core\Container\Attributes\Action;

/**
 * Fixture for deferred-trigger isolation on the container registry.
 *
 * Mirrors the production `CrossIsolationDeferRegister` case: two handlers
 * listen on the SAME target hook while deferring until DIFFERENT trigger
 * hooks — each trigger must activate ONLY its own handler.
 */
final class DeferredIsolationService
{
    public static array $log = [];

    public static function reset(): void
    {
        self::$log = [];
    }

    #[Action(hook: 'shared_destination', deferRegisterUntilHook: 'trigger_a')]
    public function handlerA(): void
    {
        self::$log[] = 'A';
    }

    #[Action(hook: 'shared_destination', deferRegisterUntilHook: 'trigger_b')]
    public function handlerB(): void
    {
        self::$log[] = 'B';
    }
}