<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Deferral;

use Closure;
use DI\Container;
use DI\ContainerBuilder;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\CallableHookParams;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\ContainerRegistryDeferredHookManager;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\ContainerRegistryHookTargetResolver;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Tests\Support\Fixtures\ExecuteIfActionService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Test suite for the `deferRegisterUntilHook` registration gate.
 *
 * deferRegisterUntilHook names a trigger hook. The entry skips the
 * registration-time registerIf gate entirely and goes straight to the
 * deferred pool; when the trigger hook fires, registerIf is re-evaluated
 * and — if it passes — the entry is activated one-shot. executeIf is only
 * evaluated at activation when its params are container-resolvable;
 * request-scoped params (e.g. \WP_Query) keep executeIf as the per-fire
 * guard.
 */
class DeferRegisterUntilHookContainerRegistryTest extends WplokerbjmTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->container()->set(ExecuteIfActionService::class, new ExecuteIfActionService());

        ExecuteIfActionService::reset();
    }

    public function testBootGateIsSkippedAndEntryIsDeferred(): void
    {
        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_until_skips_gate',
                registerIf: static fn(): bool => false,
                deferRegisterUntilHook: 'graphql_init'
            ),
        ]);
        $registry->initialize();

        // registerIf returned false at boot — but unlike a plain registerIf
        // hook, the entry is NOT dropped: it sits in the deferred pool with
        // the trigger listener armed.
        self::assertNull($this->findRegisteredHook('action', 'defer_until_skips_gate'));
        self::assertNotNull($this->findRegisteredHook('action', 'graphql_init'));
    }

    public function testTriggerFiresAndActivatesWhenRegisterIfPasses(): void
    {
        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_until_activate',
                registerIf: static fn(): bool => true,
                deferRegisterUntilHook: 'graphql_init'
            ),
        ]);
        $registry->initialize();

        // Deferred — the hook is not registered yet.
        self::assertNull($this->findRegisteredHook('action', 'defer_until_activate'));

        // The trigger hook fires (GRAPHQL_REQUEST is defined by now) and the
        // registerIf gate passes → one-shot activation.
        do_action('graphql_init');
        self::assertNotNull($this->findRegisteredHook('action', 'defer_until_activate'));

        do_action('defer_until_activate', 'hello');
        self::assertSame(['hello'], ExecuteIfActionService::$capturedValues);
    }

    public function testSchedulerIsAFilterAndPassesTriggerValueThrough(): void
    {
        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_until_filter_value',
                registerIf: static fn(): bool => true,
                deferRegisterUntilHook: 'defer_until_filter_trigger',
            ),
        ]);
        $registry->initialize();

        self::assertSame(
            'kept',
            apply_filters('defer_until_filter_trigger', 'kept'),
            'The trigger listener must preserve the filtered value.',
        );
        self::assertNotNull(
            $this->findRegisteredHook('action', 'defer_until_filter_value'),
            'Activation must still run as a filter side effect.',
        );
    }

    public function testGateFailureKeepsEntryDeferredAndListenerRearms(): void
    {
        $allow = false;
        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_until_retry',
                registerIf: static function () use (&$allow): bool {
                    return $allow;
                },
                deferRegisterUntilHook: 'graphql_init'
            ),
        ]);
        $registry->initialize();

        // First trigger fire: gate false → stays deferred, listener re-arms.
        do_action('graphql_init');
        self::assertNull($this->findRegisteredHook('action', 'defer_until_retry'));
        self::assertNotNull($this->findRegisteredHook('action', 'graphql_init'));

        // Gate flips; next trigger fire activates and the listener self-removes.
        $allow = true;
        do_action('graphql_init');
        self::assertNotNull($this->findRegisteredHook('action', 'defer_until_retry'));
        self::assertNull($this->findRegisteredHook('action', 'graphql_init'));

        do_action('defer_until_retry', 'finally');
        self::assertSame(['finally'], ExecuteIfActionService::$capturedValues);
    }

    public function testExecuteIfWithRequestScopedParamIsSkippedAtActivation(): void
    {
        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_until_executeif',
                registerIf: static fn(): bool => true,
                executeIf: static function (string $value): bool {
                    return $value === 'query';
                },
                executeIfParams: [
                    'params' => [new CallableHookParams(
                        ...['name' => 'value', 'type' => null, 'hasDefault' => false, 'default' => null],
                    )],
                ],
                hookArgs: ['value'],
                deferRegisterUntilHook: 'graphql_init'
            ),
        ]);
        $registry->initialize();

        // The executeIf param is request-scoped: no container type and no
        // default — unresolvable at activation, so executeIf is skipped and
        // the entry activates. At fire time the real fired arg is name-matched
        // against hookArgs and the gate is evaluated with it.
        do_action('graphql_init');
        self::assertNotNull($this->findRegisteredHook('action', 'defer_until_executeif'));

        do_action('defer_until_executeif', 'query');
        self::assertSame(['query'], ExecuteIfActionService::$capturedValues);
    }

    public function testTriggerClosureResolvesHookNameThroughContainer(): void
    {
        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_register_closure_trigger',
                registerIf: static fn(): bool => true,
                deferRegisterUntilHook: static fn(ExecuteIfActionService $service): string => 'graphql_init',
                deferRegisterUntilHookParams: [
                    'params' => [
                        new CallableHookParams(...['name' => 'service', 'type' => ExecuteIfActionService::class, 'hasDefault' => false, 'default' => null]),
                    ],
                ],
            ),
        ]);
        $registry->initialize();

        // The trigger closure resolves to 'graphql_init' via the container —
        // the listener is armed on the resolved name, not a closure.
        self::assertNull($this->findRegisteredHook('action', 'defer_register_closure_trigger'));
        self::assertNotNull($this->findRegisteredHook('action', 'graphql_init'));

        do_action('graphql_init');
        self::assertNotNull($this->findRegisteredHook('action', 'defer_register_closure_trigger'));

        do_action('defer_register_closure_trigger', 'dynamic');
        self::assertSame(['dynamic'], ExecuteIfActionService::$capturedValues);
    }

    public function testTriggerAlreadyFiredActivatesImmediately(): void
    {
        // Simulate the trigger hook having fired before the registry booted.
        // Real did_action() reads the $wp_actions global.
        $GLOBALS['wp_actions']['graphql_init'] = 1;

        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_until_fired',
                registerIf: static fn(): bool => true,
                deferRegisterUntilHook: 'graphql_init'
            ),
        ]);
        $registry->initialize();

        // did_action('graphql_init') is true → immediate activation, no
        // listener is ever armed.
        self::assertNotNull($this->findRegisteredHook('action', 'defer_until_fired'));
        self::assertNull($this->findRegisteredHook('action', 'graphql_init'));

        do_action('defer_until_fired', 'immediate');
        self::assertSame(['immediate'], ExecuteIfActionService::$capturedValues);
    }

    public function testActivationEarlierBeforeHookTriggerWillTriggerSchedulerUnregister(): void
    {
        $allow = false;
        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_register_closure_trigger',
                registerIf: static function () use (&$allow): bool {
                    return $allow;
                },
                deferRegisterUntilHook: 'scheduler',
            ),
        ]);
        $registry->initialize();

        self::assertNull($this->findRegisteredHook('action', 'defer_register_closure_trigger'), 'Hook must not be registered.');
        self::assertNotNull($this->findRegisteredHook('action', 'scheduler'), 'Listener must be registered after registration.');
        do_action('scheduler');
        self::assertNull($this->findRegisteredHook('action', 'defer_register_closure_trigger'), 'Hook must not be registered when gate still false.');
        $allow = true;
        $registry->activateDeferredByHook('defer_register_closure_trigger');
        // manual activation remove deferRegisterUntilHook its scheduler
        self::assertNull($this->findRegisteredHook('action', 'scheduler'), 'Listener must be removed after activation.');

        do_action('defer_register_closure_trigger', 'dynamic');
        self::assertSame(['dynamic'], ExecuteIfActionService::$capturedValues, 'Hook must be executed after activation.');
    }

    public function testSchedulerRemovalOnSameHookDoesNotSkipAdjacentPriorities(): void
    {
        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'same_hook_trigger',
                priority: PHP_INT_MIN + 2,
                registerIf: static fn(): bool => true,
                deferRegisterUntilHook: 'same_hook_trigger',
                hookArgs: ['value'],
            ),
        ]);
        $registry->initialize();

        $before = static function (): void {
            ExecuteIfActionService::$capturedValues[] = 'before';
        };
        $after = static function (): void {
            ExecuteIfActionService::$capturedValues[] = 'after';
        };

        add_action('same_hook_trigger', $before, PHP_INT_MIN + 1, 0);
        add_action('same_hook_trigger', $after, PHP_INT_MIN + 3, 0);

        do_action('same_hook_trigger', 'activated');

        self::assertArrayNotHasKey(
            PHP_INT_MIN,
            $GLOBALS['wp_filter']['same_hook_trigger']->callbacks,
            'The one-shot scheduler must remove itself without leaving its priority bucket behind.',
        );

        self::assertSame(
            ['before', 'activated', 'after'],
            ExecuteIfActionService::$capturedValues,
            'Scheduler self-removal must not skip an adjacent priority; same-hook activation must run in place.',
        );
    }

    /**
     * Build a registration array for an action hook.
     */
    private function action(
        string $class,
        string $method,
        string $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?Closure $executeIf = null,
        bool $deferRegister = false,
        ?Closure $registerIf = null,
        string|\Closure|null $deferRegisterUntilHook = null,
        array $executeIfParams = [],
        array $registerIfParams = [],
        array $tags = [],
        array $hookArgs = [],
        array $deferRegisterUntilHookParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'acceptedArgs' => $acceptedArgs,
            'deferRegister' => $deferRegister,
            'executeIf' => $executeIf,
            'executeIfParams' => $executeIfParams,
            'registerIf' => $registerIf,
            'registerIfParams' => $registerIfParams,
            'deferRegisterUntilHook' => $deferRegisterUntilHook,
            'deferRegisterUntilHookParams' => $deferRegisterUntilHookParams,
            'tags' => $tags,
            'hookArgs' => $hookArgs,
        ];
    }
}
