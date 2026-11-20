<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Lifecycle;

use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksInstanceObjectCache, WPHooksInstanceRegistry};
use WPLokerBJM\Tests\Support\Fixtures\{OnUnregisterLazyDependency, OnUnregisterProbe};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the `onUnregisterDo` lifecycle on the INSTANCE registry path
 * (attribute-discovered registrations).
 *
 * The container-side counterpart lives in {@see OnUnregisterDoContainerRegistryTest}; both
 * paths share the invoker destructor, but the ownership model differs:
 * runtime handlers are bound to an owner instance through a WeakReference and
 * their cleanup is prepared through `WPHookPlanProvider`.
 *
 * Pinned invariants:
 *  - cleanup runs exactly once when a registration is released (explicit
 *    unregister, raw `remove_action`, once-consume + queued sweep, deferred
 *    release, owner death);
 *  - cleanup never runs while a deferred entry is still parked;
 *  - cleanup parameters resolve lazily through the container, never at
 *    registration time;
 *  - a throwing cleanup never escapes the destructor;
 *  - a `registerIf`-rejected registration has no cleanup to run.
 *
 ** Note: No explicit \gc_collect_cycles() called otherwise circular reference may goes unnoticed. *
 * @see \WPLokerBJM\Core\Container\Attributes\BaseHookAttribute::$onUnregisterDo
 */
final class OnUnregisterDoInstanceRegistryTest extends WplokerbjmTestCase
{
    private ?WPHooksInstanceRegistry $registry = null;

    protected function setUp(): void
    {
        parent::setUp();

        OnUnregisterProbe::reset();
        OnUnregisterLazyDependency::$constructed = 0;

        $container = $this->container();

        // Provider-enabled registry (the production shape) so `onUnregisterDo`
        // parameters are planned and resolved lazily; the harness cleaner is
        // shared so `registeredHooks()` drives the queued sweep.
        $this->registry = $container->make(WPHooksInstanceRegistry::class, [
            'provider' => $container->get(WPHookPlanProvider::class),
            'cache' => new WPHooksInstanceObjectCache(),
            'queuedHooksCleaner' => $this->queuedHooksCleaner = new QueuedHooksCleaner()
        ]);
    }

    // ── Attribute path ────────────────────────────────────────────────

    public function testMethodAttributeCleanupFiresExactlyOnceOnExplicitUnregister(): void
    {
        $service = new class {
            public int $runs = 0;

            #[Action(hook: 'instance_on_unregister', acceptedArgs: 0, onUnregisterDo: static function (): void {
                OnUnregisterProbe::hit('method');
            })]
            public function onHook(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($service);
        self::assertNotNull($this->findRegisteredHook('action', 'instance_on_unregister'));

        $this->registry->unregisterHooksOn($service);

        self::assertSame(1, OnUnregisterProbe::count('method'), 'cleanup must run when the handler is released');
        self::assertNull($this->findRegisteredHook('action', 'instance_on_unregister'));

        $this->registry->unregisterHooksOn($service);

        self::assertSame(1, OnUnregisterProbe::count('method'), 'cleanup must never run twice');
    }

    public function testPropertyAttributeCleanupFiresOnExplicitUnregister(): void
    {
        $service = new class {
            #[Filter(hook: 'instance_on_unregister_property', acceptedArgs: 1, onUnregisterDo: static function (): void {
                OnUnregisterProbe::hit('property');
            })]
            public $propHook = null;
        };

        $this->registry->registerHooksOn($service);
        self::assertNotNull($this->findRegisteredHook('filter', 'instance_on_unregister_property'));

        $this->registry->unregisterHooksOn($service);

        self::assertSame(1, OnUnregisterProbe::count('property'));
        self::assertNull($this->findRegisteredHook('filter', 'instance_on_unregister_property'));
    }

    public function testOnceAttributeCleanupRunsOnlyAfterTheQueuedSweep(): void
    {
        $this->flushQueuedRemovalsAfterDispatch = false;

        $service = new class {
            #[Action(hook: 'instance_on_unregister_once', acceptedArgs: 0, once: true, onUnregisterDo: static function (): void {
                OnUnregisterProbe::hit('once');
            })]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($service);
        \do_action('instance_on_unregister_once');

        self::assertSame(0, OnUnregisterProbe::count('once'), 'cleanup waits until the invoker leaves $wp_filter');

        // Reading the registry drives the harness' queued-removal sweep.
        $this->flushQueuedRemovalsAfterDispatch = true;
        $this->registeredHooks();

        self::assertSame(1, OnUnregisterProbe::count('once'));
        self::assertNull($this->findRegisteredHook('action', 'instance_on_unregister_once'));
    }

    public function testRawRemoveActionTriggersCleanup(): void
    {
        $service = new class {
            #[Action(hook: 'instance_on_unregister_raw', acceptedArgs: 0, onUnregisterDo: static function (): void {
                OnUnregisterProbe::hit('raw');
            })]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($service);

        $hook = $this->findRegisteredHook('action', 'instance_on_unregister_raw');
        self::assertNotNull($hook);

        \remove_action($hook['hook'], $hook['callable'], $hook['priority']);
        unset($hook);

        self::assertSame(1, OnUnregisterProbe::count('raw'));
    }

    public function testDeferredEntryReleasedBeforeActivationIsSweptWithoutCleanup(): void
    {
        $service = new class {
            #[Action(
                hook: 'instance_on_unregister_deferred',
                acceptedArgs: 0,
                deferRegisterUntilHook: 'instance_on_unregister_trigger',
                onUnregisterDo: static function (): void {
                    OnUnregisterProbe::hit('deferred');
                },
            )]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($service);

        self::assertNull($this->findRegisteredHook('action', 'instance_on_unregister_deferred'));
        self::assertSame(0, OnUnregisterProbe::count('deferred'));

        $this->registry->unregisterHooksOn($service);

        // Mirrors the container path: a deferred entry that never reached
        // WordPress has nothing to release, so its cleanup must not run.
        self::assertSame(0, OnUnregisterProbe::count('deferred'));

        // ...but it must be swept, so its trigger cannot resurrect it.
        \do_action('instance_on_unregister_trigger');
        self::assertNull($this->findRegisteredHook('action', 'instance_on_unregister_deferred'));
    }

    public function testDeferredEntryStillParkedDoesNotRunCleanup(): void
    {
        $service = new class {
            #[Action(
                hook: 'instance_on_unregister_still_parked',
                acceptedArgs: 0,
                deferRegisterUntilHook: 'instance_on_unregister_trigger',
                onUnregisterDo: static function (): void {
                    OnUnregisterProbe::hit('parked');
                },
            )]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($service);

        self::assertSame(0, OnUnregisterProbe::count('parked'));
        self::assertNull($this->findRegisteredHook('action', 'instance_on_unregister_still_parked'));
    }

    public function testCleanupParametersResolveLazilyFromTheContainer(): void
    {
        $service = new class {
            #[Action(
                hook: 'instance_on_unregister_dependency',
                acceptedArgs: 0,
                onUnregisterDo: static function (OnUnregisterLazyDependency $dependency): void {
                    OnUnregisterProbe::hit('dependency');
                    OnUnregisterProbe::$dependency = $dependency;
                },
            )]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($service);

        self::assertSame(
            0,
            OnUnregisterLazyDependency::$constructed,
            'cleanup parameters must not be resolved during registration',
        );

        $this->registry->unregisterHooksOn($service);

        self::assertSame(1, OnUnregisterProbe::count('dependency'));
        self::assertInstanceOf(OnUnregisterLazyDependency::class, OnUnregisterProbe::$dependency);
        self::assertSame(1, OnUnregisterLazyDependency::$constructed);
    }

    public function testCleanupFailureIsSwallowed(): void
    {
        $service = new class {
            #[Action(
                hook: 'instance_on_unregister_throwing',
                acceptedArgs: 0,
                onUnregisterDo: static function (): void {
                    OnUnregisterProbe::hit('throwing');
                    throw new \RuntimeException('cleanup boom');
                },
            )]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($service);

        $this->registry->unregisterHooksOn($service);

        self::assertSame(1, OnUnregisterProbe::count('throwing'));
        self::assertNull($this->findRegisteredHook('action', 'instance_on_unregister_throwing'));
    }

    public function testRegisterIfRejectedRegistrationHasNoCleanup(): void
    {
        $service = new class {
            #[Action(
                hook: 'instance_on_unregister_rejected',
                acceptedArgs: 0,
                registerIf: static function (): bool {
                    return false;
                },
                onUnregisterDo: static function (): void {
                    OnUnregisterProbe::hit('rejected');
                },
            )]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($service);

        self::assertNull($this->findRegisteredHook('action', 'instance_on_unregister_rejected'));

        $this->registry->unregisterHooksOn($service);

        self::assertSame(0, OnUnregisterProbe::count('rejected'));
    }

    public function testOwnerDeathStillRunsCleanupWhenTheLifetimeHookIsReleased(): void
    {
        $service = new class {
            #[Action(hook: 'instance_on_unregister_lifetime', acceptedArgs: 0, onUnregisterDo: static function (): void {
                OnUnregisterProbe::hit('lifetime');
            })]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($service);

        unset($service);

        // Dispatch with a dead owner → the invoker nukes its own registration.
        \do_action('instance_on_unregister_lifetime');

        self::assertNull($this->findRegisteredHook('action', 'instance_on_unregister_lifetime'));
        self::assertSame(1, OnUnregisterProbe::count('lifetime'));
    }

    // ── Raw WordPress removal (attribute path) ────────────────────────

    public function testAttributeRegistrationCleanupFiresOnUnregisterHooksOn(): void
    {
        $owner = new class {
            #[Action(hook: 'instance_manual_on_unregister', acceptedArgs: 0, onUnregisterDo: static function (): void {
                OnUnregisterProbe::hit('manual_unregister');
            })]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($owner);

        self::assertNotNull($this->findRegisteredHook('action', 'instance_manual_on_unregister'));

        $this->registry->unregisterHooksOn($owner);

        self::assertSame(1, OnUnregisterProbe::count('manual_unregister'));
        self::assertNull($this->findRegisteredHook('action', 'instance_manual_on_unregister'));
    }

    public function testAttributeOnceCleanupRunsOnlyAfterTheQueuedSweep(): void
    {
        $this->flushQueuedRemovalsAfterDispatch = false;

        $owner = new class {
            #[Action(hook: 'instance_manual_once', acceptedArgs: 0, once: true, onUnregisterDo: static function (): void {
                OnUnregisterProbe::hit('manual_once');
            })]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($owner);

        \do_action('instance_manual_once');

        self::assertSame(0, OnUnregisterProbe::count('manual_once'));

        $this->flushQueuedRemovalsAfterDispatch = true;
        $this->registeredHooks();

        self::assertSame(1, OnUnregisterProbe::count('manual_once'));
        self::assertNull($this->findRegisteredHook('action', 'instance_manual_once'));
    }

    public function testShutdownHookPreventOnUnregisterDoRuns(): void
    {
        $cleanup = new class() {
            public int $cleanups = 0;
        };
        $owner = new class($cleanup, __FUNCTION__) extends ModuleClassHookMetadata {
            #[Action('test_onUnregisterDoCleanupDoesntRun', 11, deferRegisterUntilHook: 'shutdown', onUnregisterDo: static function (?self $self) {
                if ($self !== null) {
                    $self->parentClass->cleanups++;
                }
            })]
            private function onShutdown0()
            {
                $this->parentClass->cleanups++;
            }
            #[Action('test_onUnregisterDoCleanupDoesntRun', 10, onUnregisterDo: static function (?self $self) {
                if ($self !== null) {
                    $self->parentClass->cleanups++;
                }
            })]
            private function onShutdown1()
            {
                $this->parentClass->cleanups++;
            }

            #[Action('test_onUnregisterDoCleanupDoesntRun', 12, deferRegister: true, onUnregisterDo: static function (?self $self) {
                if ($self !== null) {
                    $self->parentClass->cleanups++;
                }
            })]
            public ?\Closure $onShutdown2 { get => $this->onShutdown2 ??= fn() => $this->parentClass->cleanups++; }
        };
        $callbackCount = static fn(): int => array_sum(array_map('count', $GLOBALS['wp_filter']['test_onUnregisterDoCleanupDoesntRun']->callbacks ?? []));
        $this->registry->registerHooksOn($owner, forceNoCache: true);

        $weakRef = \WeakReference::create($owner);

        self::assertSame(1, $callbackCount(), 'only the priority-10 should be registered');
        \add_action('shutdown', function () use ($weakRef, $callbackCount): void {
            self::assertSame(2, $callbackCount(), 'deferred priority-11 should be activated');
            $this->registry->activateDeferredByCallableInLiveInstance($weakRef->get()->onShutdown2);
            self::assertSame(3, $callbackCount(), 'deferred priority-12 should be activated');
            $this->registry->unregisterHooksOn($weakRef->get());
            do_action('test_onUnregisterDoCleanupDoesntRun');
            \remove_action('shutdown', \Closure::getCurrent(), 10);
        }, 10);
        \do_action('shutdown');
        $owner->onShutdown2 = null;
        unset($owner);
        self::assertSame(0, $cleanup->cleanups, ' hook [test_onUnregisterDoCleanupDoesntRun] should not be called after hook shutdown');
        self::assertNull($weakRef->get());
        self::assertNull($this->findRegisteredHook('action', 'test_onUnregisterDoCleanupDoesntRun'), 'Wordpress should release hook [test_onUnregisterDoCleanupDoesntRun] registration');
    }

    public function testAttributeRawRemoveActionTriggersCleanup(): void
    {
        $owner = new class {
            #[Action(hook: 'instance_manual_raw', acceptedArgs: 0, onUnregisterDo: static function (): void {
                OnUnregisterProbe::hit('manual_raw');
            })]
            public function onHook(): void {}
        };

        $this->registry->registerHooksOn($owner);

        $hook = $this->findRegisteredHook('action', 'instance_manual_raw');
        self::assertNotNull($hook);

        \remove_action($hook['hook'], $hook['callable'], $hook['priority']);
        unset($hook);

        self::assertSame(1, OnUnregisterProbe::count('manual_raw'));
    }
}
