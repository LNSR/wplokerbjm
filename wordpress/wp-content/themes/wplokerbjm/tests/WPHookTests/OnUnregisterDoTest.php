<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Tests\Support\Fixtures\OnUnregisterDependency;
use WPLokerBJM\Tests\Support\Fixtures\OnUnregisterService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the `onUnregisterDo` lifecycle on the container registry path:
 * the cleanup side effect is released exactly once, lazily, and can never
 * abort registration, dispatch or the destructor.
 ** Note: No explicit \gc_collect_cycles() called. Circular reference may goes unnoticed.
 *
 * @see \WPLokerBJM\Core\Container\Attributes\BaseHookAttribute::$onUnregisterDo
 */
final class OnUnregisterDoTest extends WplokerbjmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        OnUnregisterDependency::$constructed = 0;
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'class' => OnUnregisterService::class,
            'method' => 'onHook',
            'type' => 'action',
            'hook' => 'on_unregister_hook',
            'priority' => 10,
            'acceptedArgs' => 0,
            'deferRegister' => false,
            'executeIf' => null,
            'executeIfParams' => [],
            'registerIf' => null,
            'registerIfParams' => [],
            'onUnregisterDo' => null,
            'onUnregisterDoParams' => [],
        ], $overrides);
    }

    public function testFiresExactlyOnceWhenHookIsExplicitlyUnregistered(): void
    {
        $cleanups = 0;
        $registry = $this->createRegistry([
            $this->registration([
                'onUnregisterDo' => static function () use (&$cleanups): void {
                    $cleanups++;
                },
            ]),
        ]);
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', 'on_unregister_hook'));

        $registry->unregisterByHook('on_unregister_hook');
        self::assertSame(1, $cleanups, 'cleanup must run when the handler is released');

        $registry->unregisterByHook('on_unregister_hook');
        self::assertSame(1, $cleanups, 'cleanup must never run twice');
    }

    public function testFiresOnlyAfterAOnceHookIsPhysicallyRemovedFromWpFilter(): void
    {
        $cleanups = 0;
        $registry = $this->createRegistry([
            $this->registration([
                'once' => true,
                'onUnregisterDo' => static function () use (&$cleanups): void {
                    $cleanups++;
                },
            ]),
        ]);
        $registry->initialize();

        \do_action('on_unregister_hook');

        self::assertSame(0, $cleanups, 'physical removal is deferred until the queued sweep');

        // Reading the registry drives the harness' queued-removal sweep.
        $this->registeredHooks();

        self::assertSame(1, $cleanups, 'cleanup runs once the invoker leaves $wp_filter');
    }

    public function testDoesNotFireWhenRegisterIfRejectsTheRegistration(): void
    {
        $cleanups = 0;
        $registry = $this->createRegistry([
            $this->registration([
                'registerIf' => static fn(): bool => false,
                'onUnregisterDo' => static function () use (&$cleanups): void {
                    $cleanups++;
                },
            ]),
        ]);
        $registry->initialize();

        self::assertNull($this->findRegisteredHook('action', 'on_unregister_hook'));

        unset($registry);

        self::assertSame(0, $cleanups, 'a never-registered invoker has no cleanup to run');
    }

    public function testUnresolvableParameterNeverAbortsRegistrationOrCleanup(): void
    {
        $planProvider = new WPHookPlanProvider();
        $cleanup = static function (int $definitelyMissing): void {
            // Never reached — the parameter cannot be resolved (no container
            // entry, no default). The destructor must swallow the failure.
        };

        $registry = $this->createRegistry([
            $this->registration([
                'onUnregisterDo' => $cleanup,
                'onUnregisterDoParams' => $planProvider->buildCallablePlan($cleanup),
            ]),
        ]);
        $registry->initialize();

        self::assertNotNull(
            $this->findRegisteredHook('action', 'on_unregister_hook'),
            'a broken cleanup closure must not abort registration'
        );

        $registry->unregisterByHook('on_unregister_hook');

        self::assertNull($this->findRegisteredHook('action', 'on_unregister_hook'));
    }

    public function testRawWordPressUnregistrationTriggersOnUnregisterDo(): void
    {
        $cleanups = 0;

        $registry = $this->createRegistry([
            $this->registration([
                'onUnregisterDo' => static function () use (&$cleanups): void {
                    $cleanups++;
                },
            ]),
        ]);

        $registry->initialize();

        $hook = $this->findRegisteredHook('action', 'on_unregister_hook');
        self::assertNotNull($hook);

        $invoker = $hook['callable'];
        \remove_action($hook['hook'], $invoker, $hook['priority']);

        unset($invoker, $hook);

        self::assertSame(1, $cleanups);
    }

    public function testParametersResolveLazilyAndFromTheContainer(): void
    {
        $captured = null;
        $planProvider = new WPHookPlanProvider();
        $cleanup = static function (OnUnregisterDependency $dependency) use (&$captured): void {
            $captured = $dependency;
        };

        $registry = $this->createRegistry([
            $this->registration([
                'onUnregisterDo' => $cleanup,
                'onUnregisterDoParams' => $planProvider->buildCallablePlan($cleanup),
            ]),
        ]);
        $registry->initialize();

        self::assertSame(
            0,
            OnUnregisterDependency::$constructed,
            'cleanup parameters must not be resolved during registration'
        );

        $registry->unregisterByHook('on_unregister_hook');

        self::assertInstanceOf(OnUnregisterDependency::class, $captured);
    }

    public function testExecutionFailureIsSwallowedByTheDestructor(): void
    {
        $cleanups = 0;
        $registry = $this->createRegistry([
            $this->registration([
                'onUnregisterDo' => static function () use (&$cleanups): void {
                    $cleanups++;
                    throw new \RuntimeException('cleanup boom');
                },
            ]),
        ]);
        $registry->initialize();

        $registry->unregisterByHook('on_unregister_hook');

        self::assertSame(1, $cleanups);
        self::assertNull($this->findRegisteredHook('action', 'on_unregister_hook'));
    }

    public function testRawRemoveAllActionsTriggersOnUnregisterDo(): void
    {
        $cleanups = 0;

        $registry = $this->createRegistry([
            $this->registration([
                'onUnregisterDo' => static function () use (&$cleanups): void {
                    $cleanups++;
                },
            ]),
        ]);

        $registry->initialize();

        $hook = $this->findRegisteredHook('action', 'on_unregister_hook');
        self::assertNotNull($hook);

        $reference = \WeakReference::create($hook['callable']);

        \remove_all_actions('on_unregister_hook');

        unset($hook);

        self::assertNull($reference->get());
        self::assertSame(1, $cleanups);
    }

    public function testDeferredEntryThatNeverActivatesDoesntRunCleanup(): void
    {
        $cleanups = 0;
        $registry = $this->createRegistry([
            $this->registration([
                'deferRegister' => true,
                'onUnregisterDo' => static function () use (&$cleanups): void {
                    $cleanups++;
                },
            ]),
        ]);
        $registry->initialize();

        self::assertNull($this->findRegisteredHook('action', 'on_unregister_hook'));

        unset($registry);

        self::assertSame(
            0,
            $cleanups,
            'deferred entries shouldn\'t run cleanup'
        );
    }

    public function testShutdownHookPreventOnUnregisterDoRuns(): void
    {
        $this->flushQueuedRemovalsAfterDispatch = false;
        $cleanups = 0;

        $registry = $this->createRegistry([
            $this->registration([
                'onUnregisterDo' => static function () use (&$cleanups): void {
                    $cleanups++;
                },
            ]),
        ]);
        
        $registry->initialize();

        $hook = $this->findRegisteredHook('action', 'on_unregister_hook');
        self::assertNotNull($hook);

        \add_action('shutdown', static function () use ($hook): void {
            \remove_action($hook['hook'], $hook['callable'], $hook['priority']);
        }, 10, 0);

        \do_action('shutdown');

        self::assertNull(
            $this->findRegisteredHook('action', 'on_unregister_hook')
        );
        self::assertSame(0, $cleanups);
    }
}
