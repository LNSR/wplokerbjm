<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\Container;
use DI\ContainerBuilder;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredHookManager;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\HookTargetResolver;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHookPlanProvider;
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
class DeferRegisterUntilHookTest extends WplokerbjmTestCase
{
    private Container $container;

    private WPHookPlanProvider $planProvider;

    /** @var array<string, int> Mutable did_action state, read by the did_action mock. */
    private array $didActionStates = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->didActionStates = [];

        // The base test case does not mock did_action — alias it to a
        // mutable map so tests can simulate already-fired trigger hooks.
        \Brain\Monkey\Functions\when('did_action')->alias(function (string $hook): int {
            return $this->didActionStates[$hook] ?? 0;
        });

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            ExecuteIfActionService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();
        $this->planProvider = new WPHookPlanProvider();

        ExecuteIfActionService::reset();
    }

    public function testBootGateIsSkippedAndEntryIsDeferred(): void
    {
        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_until_skips_gate',
                registerIf: static fn (): bool => false,
                deferRegisterUntilHook: 'graphql_init'
            ),
        ], $this->container);
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
                registerIf: static fn (): bool => true,
                deferRegisterUntilHook: 'graphql_init'
            ),
        ], $this->container);
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
        ], $this->container);
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
                registerIf: static fn (): bool => true,
                executeIf: static function (string $value): bool {
                    return $value === 'query';
                },
                executeIfParams: [
                    'params' => [
                        ['name' => 'value', 'type' => null, 'hasDefault' => false, 'default' => null],
                    ],
                ],
                hookArgs: ['value'],
                deferRegisterUntilHook: 'graphql_init'
            ),
        ], $this->container);
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
                registerIf: static fn (): bool => true,
                deferRegisterUntilHook: static fn (ExecuteIfActionService $service): string => 'graphql_init',
                deferRegisterUntilHookParams: [
                    'params' => [
                        ['name' => 'service', 'type' => ExecuteIfActionService::class, 'hasDefault' => false, 'default' => null],
                    ],
                ],
            ),
        ], $this->container);
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
        $this->didActionStates['graphql_init'] = 1;

        $registry = $this->createRegistry([
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'defer_until_fired',
                registerIf: static fn (): bool => true,
                deferRegisterUntilHook: 'graphql_init'
            ),
        ], $this->container);
        $registry->initialize();

        // did_action('graphql_init') is true → immediate activation, no
        // listener is ever armed.
        self::assertNotNull($this->findRegisteredHook('action', 'defer_until_fired'));
        self::assertNull($this->findRegisteredHook('action', 'graphql_init'));

        do_action('defer_until_fired', 'immediate');
        self::assertSame(['immediate'], ExecuteIfActionService::$capturedValues);
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
            'accepted_args' => $acceptedArgs,
            'defer_register' => $deferRegister,
            'execute_if' => $executeIf,
            'execute_if_params' => $executeIfParams,
            'register_if' => $registerIf,
            'register_if_params' => $registerIfParams,
            'defer_register_until_hook' => $deferRegisterUntilHook,
            'defer_register_until_hook_params' => $deferRegisterUntilHookParams,
            'tags' => $tags,
            'hook_args' => $hookArgs,
        ];
    }
}
