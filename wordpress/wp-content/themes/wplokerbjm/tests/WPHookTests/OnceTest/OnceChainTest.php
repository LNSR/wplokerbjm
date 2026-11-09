<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\OnceTest;

use DI\Container;
use DI\ContainerBuilder;
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
        /** @suppresswarnings PHP0410 */
        $this->onceChainService = null;
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            $this->onceChainService::class => $this->onceChainService,
        ]);

        $this->container = $builder->build();
    }

    public function testEveryOnceActionOnSameHookFiresAndIsRemoved(): void
    {
        $registry = $this->createRegistry($this->chainRegistrations(), $this->container);
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', self::HOOK));

        \do_action(self::HOOK, []);

        // No priority may be skipped: all ten once-handlers fired exactly once.
        $this->assertSame(range(0, 9), $this->onceChainService->fired);
        $this->assertNull($this->findRegisteredHook('action', self::HOOK));

        // A second dispatch must not re-fire any consumed once-handler.
        \do_action(self::HOOK, []);
        $this->assertSame(range(0, 9), $this->onceChainService->fired);
    }

    public function testEveryOnceActionOnSameHookPreviousPriorityFiresAndImmediatelyRemovedAtNextPriority(): void
    {
        // No harness flush: any removal observed below must come from the
        // cleaner's direct `($this->scheduleCleanup)($invoker)` call at the
        // start of the NEXT priority, not from a post-dispatch sweep.
        $this->flushQueuedRemovalsAfterDispatch = false;

        $registry = $this->createRegistry($this->chainRegistrations(), $this->container);
        $registry->initialize();

        $assertions = [];

        for ($priority = 0; $priority <= 9; $priority++) {
            $assertions[$priority] = function () use ($priority): void {
                self::assertSame(
                    $priority,
                    \count($this->onceChainService->fired),
                    'priorities must run sequentially before their assertions'
                );

                if ($priority > 0) {
                    self::assertNull(
                        $this->hookCallbackAtPriority(self::HOOK, $priority - 1),
                        'previous priority ' . ($priority - 1) . ' must be wiped before priority ' . $priority . ' runs'
                    );
                }

                self::assertNotNull(
                    $this->hookCallbackAtPriority(self::HOOK, $priority),
                    'the currently dispatching priority ' . $priority . ' must still be registered'
                );
            };
        }

        \do_action(self::HOOK, $assertions);

        self::assertSame(range(0, 9), $this->onceChainService->fired);

        // The last priority is still queued because no further invoker ran.
        self::assertNotNull($this->hookCallbackAtPriority(self::HOOK, 9));

        // Re-enable the harness sweep and prove the queue drains.
        $this->flushQueuedRemovalsAfterDispatch = true;
        self::assertNull($this->findRegisteredHook('action', self::HOOK));
    }

    /**
     * Read the callback registered at one priority without triggering the
     * harness' queued-removal sweep.
     */
    private function hookCallbackAtPriority(string $hook, int $priority): ?callable
    {
        $callbacks = $GLOBALS['wp_filter'][$hook]->callbacks[$priority] ?? [];

        if ($callbacks === []) {
            return null;
        }

        return \reset($callbacks)['function'] ?? null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function chainRegistrations(): array
    {
        $registrations = [];

        for ($priority = 0; $priority <= 9; $priority++) {
            $registrations[] = [
                'class' => $this->onceChainService::class,
                'method' => 'p' . $priority,
                'type' => 'action',
                'hook' => self::HOOK,
                'priority' => $priority,
                'acceptedArgs' => 1,
                'once' => true,
                'deferRegister' => false,
                'executeIf' => null,
                'executeIfParams' => [],
            ];
        }

        return $registrations;
    }


    private $onceChainService {
        get {
            return $this->onceChainService ??= new class {
                /** @var list<int> */
                public array $fired = [];

                /**
                 * Assert first, then record: each assertion must observe the
                 * state left by the PREVIOUS priority, before this one appends
                 * itself to the log.
                 *
                 * @param array<int,callable> $assertions
                 */
                private function record(int $priority, array $assertions): void
                {
                    if (isset($assertions[$priority])) {
                        ($assertions[$priority])();
                    }

                    $this->fired[] = $priority;
                }

                /** @param array<int,callable> $assertions */
                public function p0(array $assertions = []): void
                {
                    $this->record(0, $assertions);
                }

                /** @param array<int,callable> $assertions */
                public function p1(array $assertions = []): void
                {
                    $this->record(1, $assertions);
                }

                /** @param array<int,callable> $assertions */
                public function p2(array $assertions = []): void
                {
                    $this->record(2, $assertions);
                }

                /** @param array<int,callable> $assertions */
                public function p3(array $assertions = []): void
                {
                    $this->record(3, $assertions);
                }

                /** @param array<int,callable> $assertions */
                public function p4(array $assertions = []): void
                {
                    $this->record(4, $assertions);
                }

                /** @param array<int,callable> $assertions */
                public function p5(array $assertions = []): void
                {
                    $this->record(5, $assertions);
                }

                /** @param array<int,callable> $assertions */
                public function p6(array $assertions = []): void
                {
                    $this->record(6, $assertions);
                }

                /** @param array<int,callable> $assertions */
                public function p7(array $assertions = []): void
                {
                    $this->record(7, $assertions);
                }

                /** @param array<int,callable> $assertions */
                public function p8(array $assertions = []): void
                {
                    $this->record(8, $assertions);
                }

                /** @param array<int,callable> $assertions */
                public function p9(array $assertions = []): void
                {
                    $this->record(9, $assertions);
                }
            };
        }
    }
}
