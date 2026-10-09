<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use ArrayObject;
use PHPUnit\Framework\TestCase;

/**
 * Drive the *real* WordPress hook engine (wp-includes/plugin.php + WP_Hook)
 * from PHPUnit, with no Brain Monkey function replacements.
 *
 * This is the reference suite for the hook-lifecycle behaviour that the rest of
 * the test-suite relies on, so it must mirror core exactly rather than a mock.
 *
 * Covered:
 *  - the engine loads and dispatches against WP_Hook
 *  - core ticket #61263: a mid-dispatch remove_action() can make WP_Hook skip
 *    the priority that immediately follows the removed one — but ONLY while an
 *    earlier (lower) priority survives; the `$current < $min` guard added to
 *    resort_active_iterations() already rescues the isolated-minimum case
 *  - the production strategy (defer the removal until the hook is no longer
 *    dispatching) keeps every consecutive priority firing
 * @link https://core.trac.wordpress.org/ticket/61263
 */
final class TestTracTicket61263 extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // WP core keeps this state around for the whole request; a test process
        // needs a clean slate for every case.
        $GLOBALS['wp_filter'] = [];
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_filters'] = [];
        $GLOBALS['wp_current_filter'] = [];
    }

    public function testRealEngineLoadsAndDispatches(): void
    {
        $state = new ArrayObject();

        add_action('spike_plain', static function () use ($state): void {
            $state['inside_doing_action'] = doing_action('spike_plain');
            $state['fired'] = true;
        }, 10);

        self::assertFalse(doing_action('spike_plain'), 'not dispatching before do_action');
        self::assertSame(0, did_action('spike_plain'));

        do_action('spike_plain');

        self::assertTrue($state['fired'] ?? false, 'callback ran');
        self::assertTrue($state['inside_doing_action'] ?? false, 'doing_action() is true inside the callback');
        self::assertSame(1, did_action('spike_plain'), 'do_action recorded once');
        self::assertFalse(doing_action('spike_plain'), 'not dispatching after do_action');
        self::assertTrue(has_action('spike_plain'), 'plain action stays registered');
    }

    /**
     * Control case: when every self-removing callback is the lowest surviving
     * priority, WP_Hook's `$current < $min` guard keeps the iteration pointer on
     * the removed priority, so the following priority is NOT skipped.
     */
    public function testIsolatedSelfRemovalDoesNotSkipFollowingPriority(): void
    {
        $log = new ArrayObject();

        foreach ([1, 2, 3, 4, 5] as $priority) {
            add_action(
                'spike_isolated',
                new SpikeOnceAction('spike_isolated', $priority, $log, deferRemovalWhileDispatching: false),
                $priority,
            );
        }

        do_action('spike_isolated');

        self::assertSame([1, 2, 3, 4, 5], $log->getArrayCopy());
    }

    /**
     * The real #61263 shape: a non-removing callback survives at a LOWER
     * priority, so the removing priorities are never the minimum remaining.
     * The `$current < $min` guard does not apply, resort_active_iterations()
     * lands the pointer past the removed priority, and the next one is skipped.
     */
    public function testSurvivingLowerPriorityTriggersFollowingPrioritySkip(): void
    {
        $log = new ArrayObject();

        add_action('spike_lower_survivor', static function () use ($log): void {
            $log[] = 0;
        }, 0);

        foreach ([1, 2, 3, 4, 5] as $priority) {
            add_action(
                'spike_lower_survivor',
                new SpikeOnceAction('spike_lower_survivor', $priority, $log, deferRemovalWhileDispatching: false),
                $priority,
            );
        }

        do_action('spike_lower_survivor');

        self::assertSame([0, 1, 3, 5], $log->getArrayCopy());
    }

    /**
     * Same shape with a surviving callback at PHP_INT_MIN — the priority the
     * production HTTPHooks::setRemoteAddr uses on muplugins_loaded.
     */
    public function testPhpIntMinSurvivorTriggersFollowingPrioritySkip(): void
    {
        $log = new ArrayObject();

        add_action('spike_min_survivor', static function () use ($log): void {
            $log[] = 'min';
        }, PHP_INT_MIN);

        foreach ([1, 2, 3, 4, 5] as $priority) {
            add_action(
                'spike_min_survivor',
                new SpikeOnceAction('spike_min_survivor', $priority, $log, deferRemovalWhileDispatching: false),
                $priority,
            );
        }

        do_action('spike_min_survivor');

        self::assertSame(['min', 1, 3, 5], $log->getArrayCopy());
    }

    /**
     * The production strategy: never call remove_action() while the hook is
     * dispatching. Every priority fires; the callbacks linger until flushed
     * outside dispatch.
     */
    public function testDeferredRemovalWhileDispatchingKeepsEveryPriority(): void
    {
        $log = new ArrayObject();

        add_action('spike_defer', static function () use ($log): void {
            $log[] = 0;
        }, 0);

        foreach ([1, 2, 3, 4, 5] as $priority) {
            add_action(
                'spike_defer',
                new SpikeOnceAction('spike_defer', $priority, $log, deferRemovalWhileDispatching: true),
                $priority,
            );
        }

        do_action('spike_defer');

        // Every priority fires because no callback removes itself while the
        // hook is dispatching; the callbacks simply linger afterwards.
        self::assertSame([0, 1, 2, 3, 4, 5], $log->getArrayCopy());
        self::assertFalse(doing_action('spike_defer'), 'dispatch finished');
        self::assertTrue(has_action('spike_defer'), 'callbacks linger until flushed outside dispatch');
    }
}

/**
 * Minimal once-style callback: logs its id and removes itself, either
 * immediately or (when configured) only once the hook is no longer dispatching.
 *
 * @internal test fixture
 */
final class SpikeOnceAction
{
    public function __construct(
        private readonly string $hook,
        private readonly int $id,
        private readonly ArrayObject $log,
        private readonly bool $deferRemovalWhileDispatching,
    ) {}

    public function __invoke(): void
    {
        $this->log[] = $this->id;

        if ($this->deferRemovalWhileDispatching && doing_action($this->hook)) {
            return; // removing now would corrupt WP_Hook's active iteration
        }

        // The priority is required — remove_action() defaults to 10, which
        // would silently miss these lower-priority registrations.
        remove_action($this->hook, $this, $this->id);
    }
}
