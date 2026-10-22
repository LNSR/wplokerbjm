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

        \do_action(self::HOOK);

        // No priority may be skipped: all ten once-handlers fired exactly once.
        $this->assertSame(range(0, 9), $this->onceChainService->fired);
        $this->assertNull($this->findRegisteredHook('action', self::HOOK));

        // A second dispatch must not re-fire any consumed once-handler.
        \do_action(self::HOOK);
        $this->assertSame(range(0, 9), $this->onceChainService->fired);
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
                'acceptedArgs' => 0,
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

                public function p0(): void
                {
                    $this->fired[] = 0;
                }

                public function p1(): void
                {
                    $this->fired[] = 1;
                }

                public function p2(): void
                {
                    $this->fired[] = 2;
                }

                public function p3(): void
                {
                    $this->fired[] = 3;
                }

                public function p4(): void
                {
                    $this->fired[] = 4;
                }

                public function p5(): void
                {
                    $this->fired[] = 5;
                }

                public function p6(): void
                {
                    $this->fired[] = 6;
                }

                public function p7(): void
                {
                    $this->fired[] = 7;
                }

                public function p8(): void
                {
                    $this->fired[] = 8;
                }

                public function p9(): void
                {
                    $this->fired[] = 9;
                }
            };
        }
    }
}
