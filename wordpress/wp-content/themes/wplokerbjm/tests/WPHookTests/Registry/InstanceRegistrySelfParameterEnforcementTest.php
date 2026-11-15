<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Registry;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Exceptions\NonNullableSelfParameterException;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksInstanceRegistry;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Instance-registry closures may run after their owner is gone or while it is
 * mid-teardown, so `self` parameters must be declared nullable. Enforcement
 * happens at registration time — before any hook or deferred entry exists.
 */
#[AllowMockObjectsWithoutExpectations]
final class InstanceRegistrySelfParameterEnforcementTest extends WplokerbjmTestCase
{
    private WPHooksInstanceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->container()->make(WPHooksInstanceRegistry::class, [
            'queuedHooksCleaner' => new QueuedHooksCleaner(),
        ]);
    }

    public function testExecuteIfWithNonNullableSelfIsRejectedAtRegistration(): void
    {
        $instance = new class {
            #[Action('self_enforcement_execute_if', executeIf: static function (self $owner): bool {
                return $owner !== null;
            })]
            public function handler(): void {}
        };

        try {
            $this->registry->registerHooksOn($instance);
            self::fail('Expected NonNullableSelfParameterException.');
        } catch (NonNullableSelfParameterException $e) {
            self::assertStringContainsString('executeIf', $e->getMessage());
            self::assertStringContainsString('$owner', $e->getMessage());
            self::assertStringContainsString('?self', $e->getMessage());
        }
    }

    public function testOnUnregisterDoWithNonNullableSelfIsRejectedAtRegistration(): void
    {
        $instance = new class {
            #[Action('self_enforcement_on_unregister', onUnregisterDo: static function (self $owner): void {
            })]
            public function handler(): void {}
        };

        try {
            $this->registry->registerHooksOn($instance);
            self::fail('Expected NonNullableSelfParameterException.');
        } catch (NonNullableSelfParameterException $e) {
            self::assertStringContainsString('onUnregisterDo', $e->getMessage());
            self::assertStringContainsString('$owner', $e->getMessage());
        }
    }

    public function testNullableSelfIsAcceptedAndReceivesTheOwner(): void
    {
        $seen = [];

        $instance = new class($seen) {
            public function __construct(private array &$seen) {}

            #[Action('self_enforcement_nullable', executeIf: static function (?self $owner): bool {
                return $owner !== null;
            })]
            public function handler(): void
            {
                $this->seen[] = 'fired';
            }
        };

        $this->registry->registerHooksOn($instance);

        do_action('self_enforcement_nullable');

        self::assertSame(['fired'], $seen);
    }
}
