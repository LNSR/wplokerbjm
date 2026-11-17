<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Deferral;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksInstanceObjectCache, WPHooksInstanceRegistry};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the plain `deferRegister` flag on the runtime/instance path.
 *
 * A deferred entry never reaches `$wp_filter` during `registerHooksOn()`.
 * Without a trigger it waits for
 * {@see WPHooksInstanceRegistry::activateDeferredByCallableInLiveInstance()};
 * with `deferRegisterUntilHook` it keeps the automatic trigger behaviour.
 *
 * The two deferred APIs are callable-scoped:
 *  - `activateDeferredByCallableInLiveInstance()` activates,
 *  - `unregisterDeferredByCallableInLiveInstance()` drops,
 * while `unregisterByCallableInLiveInstance()` now touches only the active
 * pool — the same segregation the container registry has.
 */
final class DeferRegisterInstanceRegistryTest extends WplokerbjmTestCase
{
    private WPHooksInstanceRegistry $registry;

    /** Mutable flag read by `registerIf` closures (attribute gates must be static). */
    public static bool $deferredGateOpen = false;

    protected function setUp(): void
    {
        parent::setUp();

        self::$deferredGateOpen = false;

        $this->registry = $this->container()->make(WPHooksInstanceRegistry::class, [
            'queuedHooksCleaner' => $this->queuedHooksCleaner = new QueuedHooksCleaner(),
        ]);
    }

    public function testDeferRegisterHoldsEntryUntilManualActivation(): void
    {
        $anon = new class {
            public int $runs = 0;

            #[Action('defer_register_hook', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($anon);

        self::assertNull(
            $this->findRegisteredHook('action', 'defer_register_hook'),
            'A deferRegister entry must not be registered eagerly.',
        );

        self::assertSame(
            1,
            $this->registry->activateDeferredByCallableInLiveInstance($anon->onDeferred(...)),
            'The callable-scoped activation must pick up the deferred entry.',
        );
        self::assertNotNull($this->findRegisteredHook('action', 'defer_register_hook'));

        \do_action('defer_register_hook');
        self::assertSame(1, $anon->runs);
    }

    public function testUnregisterByCallableLeavesDeferredEntriesIntact(): void
    {
        $anon = new class {
            public int $runs = 0;

            #[Action('defer_register_active_hook', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($anon);

        self::assertSame(
            0,
            $this->registry->unregisterByCallableInLiveInstance([$anon, 'onDeferred']),
            'Nothing is active yet, so the active-pool removal must be a no-op.',
        );
        self::assertNull($this->findRegisteredHook('action', 'defer_register_active_hook'));

        self::assertSame(
            1,
            $this->registry->activateDeferredByCallableInLiveInstance([$anon, 'onDeferred']),
            'Deferred entries must survive an active-pool unregister.',
        );

        \do_action('defer_register_active_hook');
        self::assertSame(1, $anon->runs);
    }

    public function testUnregisterDeferredByCallableDropsOnlyDeferredEntries(): void
    {
        $anon = new class {
            public int $runs = 0;

            #[Action('defer_register_dropped_hook', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($anon);

        self::assertSame(
            1,
            $this->registry->unregisterDeferredByCallableInLiveInstance([$anon, 'onDeferred']),
        );

        self::assertSame(
            0,
            $this->registry->activateDeferredByCallableInLiveInstance([$anon, 'onDeferred']),
            'A dropped deferred entry cannot be activated afterwards.',
        );
        self::assertNull($this->findRegisteredHook('action', 'defer_register_dropped_hook'));
    }

    public function testRegisterDeferredRechecksRegisterIfAtActivation(): void
    {
        $anon = new class {
            public int $runs = 0;

            #[Action(
                'defer_register_gated_hook',
                10,
                deferRegister: true,
                registerIf: static function (): bool {
                    return DeferRegisterInstanceRegistryTest::$deferredGateOpen;
                },
            )]
            public \Closure $onDeferred { get => fn() => $this->runs++; }
        };

        $this->registry->registerHooksOn($anon);

        self::assertSame(
            0,
            $this->registry->activateDeferredByCallableInLiveInstance($anon->onDeferred),
            'A rejecting gate leaves the entry deferred.',
        );
        self::assertNull($this->findRegisteredHook('action', 'defer_register_gated_hook'));

        self::$deferredGateOpen = true;

        self::assertSame(
            1,
            $this->registry->activateDeferredByCallableInLiveInstance($anon->onDeferred),
            'The gate is re-evaluated on every activation attempt.',
        );
        self::assertNotNull($this->findRegisteredHook('action', 'defer_register_gated_hook'));
    }

    public function testDeferredTriggerRetriesActivationWhileRegisterIfGateKeepsFailing(): void
    {
        $anon = new class {
            public int $runs = 0;

            #[Action(
                'defer_register_retry_hook',
                10,
                deferRegisterUntilHook: 'defer_register_retry_trigger',
                registerIf: static function (): bool {
                    return DeferRegisterInstanceRegistryTest::$deferredGateOpen;
                },
            )]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($anon);

        \do_action('defer_register_retry_trigger');
        self::assertNull(
            $this->findRegisteredHook('action', 'defer_register_retry_hook'),
            'A rejecting gate must leave the entry in the deferred pool.',
        );

        // The trigger listener must stay attached after the rejected attempt —
        // exactly like the container registry — so a later fire retries.
        self::$deferredGateOpen = true;

        \do_action('defer_register_retry_trigger');
        self::assertNotNull(
            $this->findRegisteredHook('action', 'defer_register_retry_hook'),
            'A later trigger fire must re-evaluate the gate and activate.',
        );

        \do_action('defer_register_retry_hook');
        self::assertSame(1, $anon->runs);
    }

    public function testActivationEarlierBeforeHookTriggerWillTriggerSchedulerUnregister(): void
    {
        $anon = new class {
            public int $runs = 0;

            #[Action(
                'defer_register_retry_hook',
                10,
                deferRegisterUntilHook: 'defer_register_retry_trigger',
                registerIf: static function (): bool {
                    return DeferRegisterInstanceRegistryTest::$deferredGateOpen;
                },
            )]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($anon);
        self::assertNotNull($this->findRegisteredHook('action', 'defer_register_retry_trigger'));
        self::$deferredGateOpen = true;
        $this->registry->activateDeferredByCallableInLiveInstance([$anon, 'onDeferred']);

        self::assertNull(
            $this->findRegisteredHook('action', 'defer_register_retry_trigger'),
            'The trigger listener must be removed after the gate opens.',
        );
        self::assertNotNull(
            $this->findRegisteredHook('action', 'defer_register_retry_hook'),
            'The deferred hook must be registered after the gate opens.',
        );
    }

    public function testDeferredPropertyRegistrationActivatesByPropertyName(): void
    {
        $anon = new class {
            public int $runs = 0;

            #[Action('defer_register_property_hook', 10, deferRegister: true)]
            public $handler;

            public function __construct()
            {
                $this->handler = function (): void {
                    $this->runs++;
                };
            }
        };

        $this->registry->registerHooksOn($anon);

        self::assertNull($this->findRegisteredHook('action', 'defer_register_property_hook'));

        self::assertSame(
            1,
            $this->registry->activateDeferredByCallableInLiveInstance([$anon, 'handler']),
            'Property hooks are addressed by property name.',
        );

        \do_action('defer_register_property_hook');
        self::assertSame(1, $anon->runs);
    }

    public function testStackedDeferredAttributesActivateTogether(): void
    {
        $anon = new class {
            public int $runs = 0;

            #[Action('defer_register_stacked_hook', 10, deferRegister: true)]
            #[Action('defer_register_stacked_hook', 20, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($anon);

        self::assertNull($this->findRegisteredHook('action', 'defer_register_stacked_hook'));

        self::assertSame(
            2,
            $this->registry->activateDeferredByCallableInLiveInstance([$anon, 'onDeferred']),
            'Every stacked attribute is its own deferred entry.',
        );

        \do_action('defer_register_stacked_hook');
        self::assertSame(2, $anon->runs);
    }

    public function testCacheHitPreservesDeferRegister(): void
    {
        $cache = new WPHooksInstanceObjectCache();

        $factory = static fn(): ModuleClassHookMetadata => new class extends ModuleClassHookMetadata {
            public function __construct()
            {
                parent::__construct('DeferRegisterCacheHost', 'hooks');
            }

            #[Action('defer_register_cache_hook', 10, deferRegister: true)]
            public function onDeferred(): void {}
        };

        $registry = $this->container()->make(WPHooksInstanceRegistry::class, [
            'cache' => $cache,
            'queuedHooksCleaner' => $this->queuedHooksCleaner,
        ]);

        $first = $factory();
        $registry->registerHooksOn($first);

        self::assertNull($this->findRegisteredHook('action', 'defer_register_cache_hook'));
        self::assertNotNull($cache->get('DeferRegisterCacheHost', 'hooks'));

        // Cache-hit path — must not lose the flag.
        $second = $factory();
        $registry->registerHooksOn($second);

        self::assertNull(
            $this->findRegisteredHook('action', 'defer_register_cache_hook'),
            'A cache hit must re-hydrate the deferRegister entry instead of registering it eagerly.',
        );

        self::assertSame(
            1,
            $registry->activateDeferredByCallableInLiveInstance([$second, 'onDeferred']),
        );
        self::assertNotNull($this->findRegisteredHook('action', 'defer_register_cache_hook'));
    }
}
