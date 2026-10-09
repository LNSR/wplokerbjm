<?php
declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use DI\Container;
use DI\ContainerBuilder;
use WPLokerBJM\Tests\Support\Fixtures\OnceChainService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Regression coverage for the once-hook dispatch interaction.
 *
 * WordPress snapshots the priority list when a hook starts dispatching and
 * repositions its internal iteration pointer whenever a callback removes
 * itself from the same hook (WP_Hook::resort_active_iterations). A once-hook
 * that unregisters itself during dispatch therefore used to make the
 * immediately-following priority be skipped.
 *
 * The production `Test` fixture (ten `once: true` actions on `plugins_loaded`
 * at priorities 0..9) exposed that: only the odd priorities fired. These tests
 * reproduce the same shape on a synthetic hook and assert that:
 *  - every once-hook on the chain fires (no priority is skipped), and
 *  - the WordPress-side removals are queued during dispatch then applied once
 *    the dispatch completed.
 */
final class OnceChainTest extends WplokerbjmTestCase
{
    private const HOOK = 'once_chain';

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            OnceChainService::class => \DI\autowire(),
        ]);

        $this->container = $builder->build();

        OnceChainService::reset();
    }

    public function testEveryOnceActionOnSameHookFiresAndIsRemoved(): void
    {
        $registry = $this->createRegistry($this->chainRegistrations(), $this->container);
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', self::HOOK));

        \do_action(self::HOOK);

        // No priority may be skipped: all ten once-handlers fired exactly once.
        $this->assertSame(range(0, 9), OnceChainService::$fired);
        $this->assertNull($this->findRegisteredHook('action', self::HOOK));

        // A second dispatch must not re-fire any consumed once-handler.
        \do_action(self::HOOK);
        $this->assertSame(range(0, 9), OnceChainService::$fired);
    }

    public function testQueuedRemovalsLingerUntilExplicitFlush(): void
    {
        // Keep the production-like queue so we can observe the deferred removal
        // instead of letting the harness flush it at the end of the dispatch.
        $this->flushQueuedRemovalsAfterDispatch = false;

        $registry = $this->createRegistry($this->chainRegistrations(), $this->container);
        $registry->initialize();

        \do_action(self::HOOK);

        $this->assertSame(range(0, 9), OnceChainService::$fired);

        // The WordPress-side hooks must still be registered while queued.
        $this->assertNotNull($this->findRegisteredHook('action', self::HOOK));
        $this->assertCount(10, $registry->queuedRemovalEntry[self::HOOK] ?? []);

        $this->flushQueuedRemovals([$registry]);

        $this->assertNull($this->findRegisteredHook('action', self::HOOK));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function chainRegistrations(): array
    {
        $registrations = [];

        for ($priority = 0; $priority <= 9; $priority++) {
            $registrations[] = [
                'class' => OnceChainService::class,
                'method' => 'p' . $priority,
                'type' => 'action',
                'hook' => self::HOOK,
                'priority' => $priority,
                'acceptedArgs' => 0,
                'once' => true,
                'deferRegister' => false,
                'executeIf' => null,
                'executeIfParams' => [],
            ];
        }

        return $registrations;
    }
}
