<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Registry;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksInstanceRegistry;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the owner-scoped hook selectors on the runtime/instance path:
 * `activateDeferredByHookInLiveInstance()` /
 * `unregisterDeferredByHookInLiveInstance()` /
 * `unregisterByHookInLiveInstance()`.
 *
 * The container selectors operate on a global registry. The instance
 * counterparts operate on one live owner only — a sibling instance exposing
 * the same hook must stay untouched.
 */
final class InstanceRegistryHookSelectorTest extends WplokerbjmTestCase
{
    private WPHooksInstanceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->container()->make(WPHooksInstanceRegistry::class, [
            'queuedHooksCleaner' => $this->queuedHooksCleaner = new QueuedHooksCleaner(),
        ]);
    }

    public function testActivateDeferredByHookWakesOnlyTheMatchingHookForTheOwner(): void
    {
        $instance = new class {
            public int $first = 0;
            public int $second = 0;

            #[Action('instance_hook_selector_first', 10, deferRegister: true)]
            public function onFirst(): void
            {
                $this->first++;
            }

            #[Action('instance_hook_selector_second', 20, deferRegister: true)]
            public function onSecond(): void
            {
                $this->second++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertNull($this->findRegisteredHook('action', 'instance_hook_selector_first'));
        self::assertNull($this->findRegisteredHook('action', 'instance_hook_selector_second'));

        self::assertSame(
            1,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_hook_selector_first'),
        );

        self::assertNotNull($this->findRegisteredHook('action', 'instance_hook_selector_first'));
        self::assertNull(
            $this->findRegisteredHook('action', 'instance_hook_selector_second'),
            'A different hook on the same owner must stay deferred.',
        );

        \do_action('instance_hook_selector_first');
        \do_action('instance_hook_selector_second');

        self::assertSame([1, 0], [$instance->first, $instance->second]);

        self::assertSame(
            0,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_hook_selector_first'),
            'An already-active hook must not activate twice.',
        );
    }

    public function testActivateDeferredByHookLeavesSiblingInstancesDeferred(): void
    {
        $factory = static fn(): object => new class {
            public int $runs = 0;

            #[Action('instance_hook_selector_sibling', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $first = $factory();
        $second = $factory();

        $this->registry->registerHooksOn($first);
        $this->registry->registerHooksOn($second);

        self::assertSame(
            1,
            $this->registry->activateDeferredByHookInLiveInstance($first, 'instance_hook_selector_sibling'),
        );

        \do_action('instance_hook_selector_sibling');
        self::assertSame([1, 0], [$first->runs, $second->runs], 'Only the addressed owner may activate.');

        self::assertSame(
            1,
            $this->registry->activateDeferredByHookInLiveInstance($second, 'instance_hook_selector_sibling'),
        );

        \do_action('instance_hook_selector_sibling');
        self::assertSame([2, 1], [$first->runs, $second->runs]);
    }

    public function testUnregisterDeferredByHookDropsOnlyMatchingDeferredRegistrations(): void
    {
        $instance = new class {
            public int $runs = 0;

            #[Action('instance_hook_selector_active', 10)]
            public function onActive(): void
            {
                $this->runs++;
            }

            #[Action('instance_hook_selector_dropped', 10, deferRegister: true)]
            public function onDropped(): void
            {
                $this->runs++;
            }

            #[Action('instance_hook_selector_kept', 10, deferRegister: true)]
            public function onKept(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertNotNull($this->findRegisteredHook('action', 'instance_hook_selector_active'));
        self::assertNull($this->findRegisteredHook('action', 'instance_hook_selector_dropped'));
        self::assertNull($this->findRegisteredHook('action', 'instance_hook_selector_kept'));

        self::assertSame(
            1,
            $this->registry->unregisterDeferredByHookInLiveInstance($instance, 'instance_hook_selector_dropped'),
        );

        self::assertSame(
            0,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_hook_selector_dropped'),
            'A dropped deferred hook cannot activate later.',
        );
        self::assertSame(
            1,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_hook_selector_kept'),
            'Other deferred hooks owned by the instance are untouched.',
        );

        self::assertNotNull(
            $this->findRegisteredHook('action', 'instance_hook_selector_active'),
            'Active registrations are untouched by the deferred selector.',
        );
    }

    public function testUnregisterByHookRemovesOnlyMatchingActiveRegistrations(): void
    {
        $instance = new class {
            public int $runs = 0;

            #[Action('instance_hook_selector_remove', 10)]
            #[Action('instance_hook_selector_remove', 20)]
            public function onRemoved(): void
            {
                $this->runs++;
            }

            #[Action('instance_hook_selector_survivor', 10)]
            public function onSurvivor(): void
            {
                $this->runs++;
            }

            #[Action('instance_hook_selector_deferred_survivor', 10, deferRegister: true)]
            public function onDeferredSurvivor(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertSame(
            2,
            $this->registry->unregisterByHookInLiveInstance($instance, 'instance_hook_selector_remove'),
            'Every priority of the matching hook is removed.',
        );

        self::assertNull($this->findRegisteredHook('action', 'instance_hook_selector_remove'));
        self::assertNotNull($this->findRegisteredHook('action', 'instance_hook_selector_survivor'));
        self::assertNull($this->findRegisteredHook('action', 'instance_hook_selector_deferred_survivor'));

        self::assertSame(
            1,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_hook_selector_deferred_survivor'),
            'The active-pool selector must not sweep deferred registrations.',
        );

        \do_action('instance_hook_selector_survivor');
        \do_action('instance_hook_selector_deferred_survivor');
        self::assertSame(2, $instance->runs);
    }

    public function testUnregisterByHookLeavesSiblingInstanceActive(): void
    {
        $factory = static fn(): object => new class {
            public int $runs = 0;

            #[Action('instance_hook_selector_sibling_active', 10)]
            public function onActive(): void
            {
                $this->runs++;
            }
        };

        $first = $factory();
        $second = $factory();

        $this->registry->registerHooksOn($first);
        $this->registry->registerHooksOn($second);

        self::assertSame(
            1,
            $this->registry->unregisterByHookInLiveInstance($first, 'instance_hook_selector_sibling_active'),
        );

        \do_action('instance_hook_selector_sibling_active');
        self::assertSame([0, 1], [$first->runs, $second->runs], 'Only the addressed owner may be swept.');

        self::assertSame(
            1,
            $this->registry->unregisterByHookInLiveInstance($second, 'instance_hook_selector_sibling_active'),
        );

        \do_action('instance_hook_selector_sibling_active');
        self::assertSame([0, 1], [$first->runs, $second->runs]);
    }

    public function testUnregisterDeferredByHookLeavesSiblingInstanceDeferred(): void
    {
        $factory = static fn(): object => new class {
            public int $runs = 0;

            #[Action('instance_hook_selector_sibling_deferred', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $first = $factory();
        $second = $factory();

        $this->registry->registerHooksOn($first);
        $this->registry->registerHooksOn($second);

        self::assertSame(
            1,
            $this->registry->unregisterDeferredByHookInLiveInstance($first, 'instance_hook_selector_sibling_deferred'),
        );

        self::assertSame(
            0,
            $this->registry->activateDeferredByHookInLiveInstance($first, 'instance_hook_selector_sibling_deferred'),
        );
        self::assertSame(
            1,
            $this->registry->activateDeferredByHookInLiveInstance($second, 'instance_hook_selector_sibling_deferred'),
        );

        \do_action('instance_hook_selector_sibling_deferred');
        self::assertSame([0, 1], [$first->runs, $second->runs]);
    }

    public function testActivateDeferredByHookPatternWakesOnlyMatchingHooksForOwner(): void
    {
        $factory = static fn(): object => new class {
            public int $mailOne = 0;
            public int $mailTwo = 0;
            public int $news = 0;

            #[Action('instance_pattern_mail_one', 10, deferRegister: true)]
            public function onMailOne(): void
            {
                $this->mailOne++;
            }

            #[Action('instance_pattern_mail_two', 20, deferRegister: true)]
            public function onMailTwo(): void
            {
                $this->mailTwo++;
            }

            #[Action('instance_pattern_news', 10, deferRegister: true)]
            public function onNews(): void
            {
                $this->news++;
            }
        };

        $first = $factory();
        $second = $factory();

        $this->registry->registerHooksOn($first);
        $this->registry->registerHooksOn($second);

        self::assertSame(
            2,
            $this->registry->activateDeferredByHookPatternInLiveInstance($first, 'instance_pattern_mail_*'),
        );

        \do_action('instance_pattern_mail_one');
        \do_action('instance_pattern_mail_two');
        \do_action('instance_pattern_news');

        self::assertSame([1, 1, 0], [$first->mailOne, $first->mailTwo, $first->news]);
        self::assertSame([0, 0, 0], [$second->mailOne, $second->mailTwo, $second->news]);

        self::assertSame(
            2,
            $this->registry->activateDeferredByHookPatternInLiveInstance($second, 'instance_pattern_mail_*'),
        );

        \do_action('instance_pattern_mail_one');
        \do_action('instance_pattern_mail_two');
        \do_action('instance_pattern_news');

        self::assertSame([2, 2, 0], [$first->mailOne, $first->mailTwo, $first->news]);
        self::assertSame([1, 1, 0], [$second->mailOne, $second->mailTwo, $second->news]);
    }

    public function testUnregisterDeferredByHookPatternDropsOnlyMatchingDeferredHooks(): void
    {
        $instance = new class {
            public int $runs = 0;

            #[Action('instance_pattern_drop_mail', 10, deferRegister: true)]
            public function onMail(): void
            {
                $this->runs++;
            }

            #[Action('instance_pattern_drop_news', 10, deferRegister: true)]
            public function onNews(): void
            {
                $this->runs++;
            }

            #[Action('instance_pattern_drop_active', 10)]
            public function onActive(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertSame(
            1,
            $this->registry->unregisterDeferredByHookPatternInLiveInstance($instance, 'instance_pattern_drop_mail*'),
        );

        self::assertSame(
            0,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_pattern_drop_mail'),
        );
        self::assertSame(
            1,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_pattern_drop_news'),
            'Deferred hooks outside the pattern must survive.',
        );
        self::assertNotNull(
            $this->findRegisteredHook('action', 'instance_pattern_drop_active'),
            'Active hooks are untouched by the deferred pattern selector.',
        );
    }

    public function testUnregisterByHookPatternRemovesOnlyMatchingActiveHooks(): void
    {
        $instance = new class {
            public int $runs = 0;

            #[Action('instance_pattern_active_mail', 10)]
            #[Action('instance_pattern_active_mail', 20)]
            public function onMail(): void
            {
                $this->runs++;
            }

            #[Action('instance_pattern_active_news', 10)]
            public function onNews(): void
            {
                $this->runs++;
            }

            #[Action('instance_pattern_active_mail_deferred', 10, deferRegister: true)]
            public function onDeferredMail(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertSame(
            2,
            $this->registry->unregisterByHookPatternInLiveInstance($instance, 'instance_pattern_active_mail*'),
        );

        self::assertNull($this->findRegisteredHook('action', 'instance_pattern_active_mail'));
        self::assertNotNull($this->findRegisteredHook('action', 'instance_pattern_active_news'));
        self::assertNull($this->findRegisteredHook('action', 'instance_pattern_active_mail_deferred'));

        self::assertSame(
            1,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_pattern_active_mail_deferred'),
            'The active pattern selector must not sweep deferred entries.',
        );
    }

    public function testUnregisterByClassInLiveInstanceRemovesEveryActiveRegistrationForOwner(): void
    {
        $instance = new class {
            public int $runs = 0;

            #[Action('instance_class_active_a', 10)]
            #[Action('instance_class_active_a', 20)]
            public function onA(): void
            {
                $this->runs++;
            }

            #[Action('instance_class_active_b', 10)]
            public function onB(): void
            {
                $this->runs++;
            }

            #[Action('instance_class_deferred', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $sibling = new class {
            public int $runs = 0;

            #[Action('instance_class_sibling_active', 10)]
            public function onSibling(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($instance);
        $this->registry->registerHooksOn($sibling);

        self::assertSame(3, $this->registry->unregisterByClassInLiveInstance($instance));

        self::assertNull($this->findRegisteredHook('action', 'instance_class_active_a'));
        self::assertNull($this->findRegisteredHook('action', 'instance_class_active_b'));
        self::assertNull($this->findRegisteredHook('action', 'instance_class_deferred'));

        self::assertSame(
            1,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_class_deferred'),
            'Class-scoped active removal must leave the deferred pool untouched.',
        );

        \do_action('instance_class_active_a');
        \do_action('instance_class_sibling_active');
        self::assertSame([0, 1], [$instance->runs, $sibling->runs], 'Only the addressed owner may be swept.');
    }

    public function testHookPatternSelectorsRejectInvalidPatterns(): void
    {
        $instance = new class {
            #[Action('instance_pattern_invalid', 10, deferRegister: true)]
            public function onDeferred(): void {}
        };

        $this->registry->registerHooksOn($instance);

        $this->expectException(\InvalidArgumentException::class);

        $this->registry->activateDeferredByHookPatternInLiveInstance($instance, 'invalid');
    }

    public function testHookSelectorsReturnZeroForUnknownHook(): void
    {
        $instance = new class {
            #[Action('instance_hook_selector_known', 10, deferRegister: true)]
            public function onKnown(): void {}
        };

        $this->registry->registerHooksOn($instance);

        self::assertSame(
            0,
            $this->registry->activateDeferredByHookInLiveInstance($instance, 'instance_hook_selector_missing'),
        );
        self::assertSame(
            0,
            $this->registry->unregisterDeferredByHookInLiveInstance($instance, 'instance_hook_selector_missing'),
        );
        self::assertSame(
            0,
            $this->registry->unregisterByHookInLiveInstance($instance, 'instance_hook_selector_missing'),
        );
    }
}
