<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\OnceTest;

use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Tests\Support\Fixtures\NestedOnceService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Regression guard for deep nested `once` hooks.
 *
 * An outer once-hook dispatches a mid hook, which dispatches an inner hook —
 * and the inner handler re-enters the OUTER hook while the whole ancestry is
 * still on the dispatch stack.
 *
 * Pinned invariants:
 *  - every once-handler runs EXACTLY once (the nested re-entry is swallowed by
 *    the consumed guard, not re-dispatched);
 *  - the invocation order is outer → mid → inner;
 *  - each inner invocation immediately cleans the queued OUTER once-entries,
 *    so none of the three hooks survive in `$wp_filter` once the chain unwinds.
 */
final class NestedOnceDepthTest extends WplokerbjmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        /** @suppresswarnings PHP0410 */
        $this->nestedOnceService = null;
        $this->container()->set($this->nestedOnceService::class, $this->nestedOnceService);
        $this->flushQueuedRemovalsAfterDispatch = false;
    }

    private $nestedOnceService {
        get {
            return $this->nestedOnceService ??= new class() {
                public array $log = [];

                public int $outerRuns = 0;

                public int $midRuns = 0;

                public int $innerRuns = 0;
                private bool $alreadyRun = false;

                public function outer(array $assertCallback): void
                {
                    if ($this->alreadyRun) $assertCallback[2](); // shouldnt run
                    $this->outerRuns++;
                    $this->log[] = 'outer';
                    \do_action('nested_mid', $assertCallback);
                    \do_action('nested_outer', $assertCallback);
                }

                public function mid(array $assertCallback): void
                {
                    $assertCallback[0]();
                    $this->midRuns++;
                    $this->log[] = 'mid';
                    \do_action('nested_inner', $assertCallback);
                }

                public function inner(array $assertCallback): void
                {
                    $assertCallback[1]();
                    $this->innerRuns++;
                    $this->log[] = 'inner';
                    $this->alreadyRun = true;

                    // Re-enter the outer hook while every ancestor is still on the stack.
                    \do_action('nested_outer', $assertCallback);
                }
            };
        }
    }

    public function testNestedOnceChainRunsEachHandlerExactlyOnceAndCleansOuterEntriesImmediately(): void
    {
        $registry = $this->createRegistry($this->nestedRegistrations());
        $registry->initialize();

        // The outer hook is active; the deepest deferred descendant is not
        // registered yet. (`nested_mid` intentionally carries the PHP_INT_MIN
        // deferred-activation listener for `nested_inner`, so it is not
        // asserted null here — its once handler is what must be absent.)
        $this->assertNotNull($this->findRegisteredHook('action', 'nested_outer'));
        $this->assertNull($this->findRegisteredHook('action', 'nested_inner'));

        $assertCallback = [
            fn() => $this->assertNull($this->findRegisteredHook('action', 'nested_outer')),
            fn() => $this->assertNull($this->findRegisteredHook('action', 'nested_mid')),
            $this->never(...),
        ];
        \do_action('nested_outer', $assertCallback);
        $this->assertNull($this->findRegisteredHook('action', 'nested_inner'));

        // Each once-handler ran exactly once — the deepest re-entry of
        // `nested_outer` (from inner()) did NOT re-run the outer handler.
        $this->assertSame(1, $this->nestedOnceService->outerRuns);
        $this->assertSame(1, $this->nestedOnceService->midRuns);
        $this->assertSame(1, $this->nestedOnceService->innerRuns);
        $this->assertSame(['outer', 'mid', 'inner'], $this->nestedOnceService->log);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function nestedRegistrations(): array
    {
        return [
            [
                'class' => $this->nestedOnceService::class,
                'method' => 'outer',
                'type' => 'action',
                'hook' => 'nested_outer',
                'priority' => 10,
                'acceptedArgs' => 1,
                'once' => true,
                'deferRegister' => false,
                'executeIf' => null,
                'executeIfParams' => [],
            ],
            [
                'class' => $this->nestedOnceService::class,
                'method' => 'mid',
                'type' => 'action',
                'hook' => 'nested_mid',
                'priority' => 10,
                'acceptedArgs' => 1,
                'once' => true,
                'deferRegister' => true,
                'deferRegisterUntilHook' => 'nested_outer',
                'executeIf' => null,
                'executeIfParams' => [],
            ],
            [
                'class' => $this->nestedOnceService::class,
                'method' => 'inner',
                'type' => 'action',
                'hook' => 'nested_inner',
                'priority' => 10,
                'acceptedArgs' => 1,
                'once' => true,
                'deferRegister' => true,
                'deferRegisterUntilHook' => 'nested_mid',
                'executeIf' => null,
                'executeIfParams' => [],
            ],
        ];
    }
}
