<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use DI\Container;
use DI\ContainerBuilder;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksInstanceRegistry;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Regression guard for deferred-trigger isolation.
 *
 * Two entries may listen on the SAME target hook while deferring until
 * DIFFERENT trigger hooks. Each trigger must activate ONLY its own entry —
 * activating by the shared target-hook bucket would wake every entry that
 * listens on that hook name (the production `CrossIsolationDeferRegister`
 * case). Covered for the instance registry (attribute + manual paths) and
 * the container registry.
 */
final class DeferredTriggerIsolationTest extends WplokerbjmTestCase
{
    private WPHooksInstanceRegistry $registry;
    private Container $serviceContainer;

    protected function setUp(): void
    {
        parent::setUp();
        /** @suppresswarnings PHP0410 */
        $this->deferredIsolationService = null;
        $this->registry = $this->container()->make(WPHooksInstanceRegistry::class, ['provider' => null]);
        $this->trackRegistry($this->registry);

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            $this->deferredIsolationService::class => $this->deferredIsolationService,
        ]);
        $this->serviceContainer = $builder->build();
    }

    public function testEachAttributeDeferredEntryActivatesOnlyOnItsOwnTrigger(): void
    {
        $log = [];

        $instance = new class($log) {
            public function __construct(private array &$log) {}

            #[Action(hook: 'shared_destination', deferRegisterUntilHook: 'trigger_a')]
            public function handlerA(): void
            {
                $this->log[] = 'A';
            }

            #[Action(hook: 'shared_destination', deferRegisterUntilHook: 'trigger_b')]
            public function handlerB(): void
            {
                $this->log[] = 'B';
            }
        };

        $this->registry->registerHooksOn($instance);

        // Both entries are deferred: nothing registered on the shared target hook yet.
        $this->assertNull($this->findRegisteredHook('action', 'shared_destination'));

        // Fire trigger_b only — handlerB alone must activate.
        do_action('trigger_b');
        $this->assertNotNull($this->findRegisteredHook('action', 'shared_destination'));

        do_action('shared_destination');
        $this->assertSame(['B'], $log, 'handlerA must stay deferred until trigger_a fires');

        // Fire trigger_a — handlerA now joins. Both sit at priority 10, so the
        // dispatch order follows registration order: handlerB (activated at
        // trigger_b first) then handlerA.
        do_action('trigger_a');
        do_action('shared_destination');
        $this->assertSame(['B', 'B', 'A'], $log);
    }

    public function testEachManualDeferredEntryActivatesOnlyOnItsOwnTrigger(): void
    {
        $log = [];

        // Static closures carry no bound `$this`, so the owner cannot be
        // inferred — pass it explicitly.
        $owner = new \stdClass();

        // Both entries share hook+type+priority+acceptedArgs. Their uniqueness
        // comes from the per-callback fingerprint folded into the manual
        // HookKey; without it the second entry would overwrite the first in
        // the deferred pool and never register.
        $this->registry->registerAction(
            'manual_shared',
            static function () use (&$log): void {
                $log[] = 'MA';
            },
            deferRegisterUntilHook: 'manual_trigger_a',
            owner: $owner,
        );

        $this->registry->registerAction(
            'manual_shared',
            static function () use (&$log): void {
                $log[] = 'MB';
            },
            deferRegisterUntilHook: 'manual_trigger_b',
            owner: $owner,
        );

        $this->assertNull($this->findRegisteredHook('action', 'manual_shared'));

        do_action('manual_trigger_b');
        do_action('manual_shared');
        $this->assertSame(['MB'], $log, 'the trigger_a entry must stay deferred');

        do_action('manual_trigger_a');
        do_action('manual_shared');
        $this->assertSame(['MB', 'MB', 'MA'], $log);
    }

    public function testEachContainerDeferredEntryActivatesOnlyOnItsOwnTrigger(): void
    {
        $registrations = [
            [
                'class' => $this->deferredIsolationService::class,
                'method' => 'handlerA',
                'type' => 'action',
                'hook' => 'shared_destination',
                'priority' => 10,
                'acceptedArgs' => 1,
                'deferRegisterUntilHook' => 'trigger_a',
            ],
            [
                'class' => $this->deferredIsolationService::class,
                'method' => 'handlerB',
                'type' => 'action',
                'hook' => 'shared_destination',
                'priority' => 10,
                'acceptedArgs' => 1,
                'deferRegisterUntilHook' => 'trigger_b',
            ],
        ];

        $registry = $this->createRegistry($registrations, $this->serviceContainer);
        $registry->initialize();

        $this->assertNull($this->findRegisteredHook('action', 'shared_destination'));

        do_action('trigger_b');
        $this->assertNotNull($this->findRegisteredHook('action', 'shared_destination'));

        do_action('shared_destination');
        $this->assertSame(['B'], $this->deferredIsolationService->log, 'handlerA must stay deferred until trigger_a fires');

        do_action('trigger_a');
        do_action('shared_destination');
        $this->assertSame(['B', 'B', 'A'], $this->deferredIsolationService->log);
    }

    private $deferredIsolationService {
        get {
            return $this->deferredIsolationService ??= new class() {
                public array $log = [];

                #[Action(hook: 'shared_destination', deferRegisterUntilHook: 'trigger_a')]
                public function handlerA(): void
                {
                    $this->log[] = 'A';
                }

                #[Action(hook: 'shared_destination', deferRegisterUntilHook: 'trigger_b')]
                public function handlerB(): void
                {
                    $this->log[] = 'B';
                }
            };
        }
    }
}
