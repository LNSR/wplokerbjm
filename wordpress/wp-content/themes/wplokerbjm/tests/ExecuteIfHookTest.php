<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\ContainerBuilder;
use DI\Container;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, HookRuntimeResolver, WPHooksContainerRegistry, WPHooksRuntimeRegistry, HookTargetResolver};
use WPLokerBJM\Core\Container\Support\WPHooks\{Provider\WPHookPlanProvider};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Tests\Support\Fixtures\ExecuteIfActionService;
use WPLokerBJM\Tests\Support\Fixtures\ExecuteIfFilterService;
use WPLokerBJM\Tests\Support\Fixtures\RuntimeExecuteIfService;

/**
 * Test suite for the `condition` gate on hook registrations.
 *
 * Verifies that:
 *  - The condition closure receives the DI container and may resolve services.
 *  - A `true` condition fires the hook as usual.
 *  - A `false` condition skips the hook entirely — the target service is
 *    never resolved from the container.
 *  - The gate predates deferral: even after a deferred hook is activated,
 *    a `false` condition still suppresses it.
 *  - A condition that throws is caught, logged, and falls back to the
 *    standard filter passthrough / action no-op contract.
 *  - A non-bool condition result is treated as a failure (logged + skip).
 *  - WPHooksRuntimeRegistry skips condition-gated hooks with a warning,
 *    since runtime-registered instances have no container access.
 */
class ExecuteIfHookTest extends WplokerbjmTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh, isolated container per test — only the condition fixtures.
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            ExecuteIfActionService::class => \DI\autowire(),
            ExecuteIfFilterService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();

        ExecuteIfActionService::reset();
        ExecuteIfFilterService::reset();
        RuntimeExecuteIfService::reset();
    }

    public function testExecuteIfReceivesContainerAndFiresHook(): void
    {
        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'condition_action',
                executeIf: static function (ContainerInterface $c): bool {
                    // The gate must receive the DI container and be able
                    // to query it for services.
                    return $c->has(ExecuteIfActionService::class);
                }
            ),
        ];

        $targetResolver = new HookTargetResolver();
        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        do_action('condition_action', 'hello');

        $this->assertSame(1, ExecuteIfActionService::$instantiationCount);
        $this->assertSame(['hello'], ExecuteIfActionService::$capturedValues);
    }

    public function testExecuteIfFalseSkipsHookWithoutResolvingService(): void
    {
        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'condition_action',
                executeIf: static fn (ContainerInterface $c): bool => false
            ),
        ];

        $targetResolver = new HookTargetResolver();
        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', 'condition_action'));

        do_action('condition_action', 'hello');

        $this->assertSame(0, ExecuteIfActionService::$instantiationCount);
        $this->assertSame([], ExecuteIfActionService::$capturedValues);
    }

    public function testExecuteIfFalseSkipsDeferredHookEvenAfterActivation(): void
    {
        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'condition_deferred',
                deferRegister: true,
                executeIf: static fn (ContainerInterface $c): bool => false
            ),
        ];

        $targetResolver = new HookTargetResolver();
        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        // Deferred: not registered during initialize()...
        $this->assertNull($this->findRegisteredHook('action', 'condition_deferred'));

        // ...activated on demand, but the condition gate still applies.
        $registry->activateDeferredByHook('condition_deferred');
        $this->assertNotNull($this->findRegisteredHook('action', 'condition_deferred'));

        do_action('condition_deferred', 'hello');

        $this->assertSame(0, ExecuteIfActionService::$instantiationCount);
        $this->assertSame([], ExecuteIfActionService::$capturedValues);
    }

    public function testExecuteIfExceptionLogsErrorAndPassesFilterThrough(): void
    {
        $registrations = [
            $this->filter(
                ExecuteIfFilterService::class,
                'onExecuteIfFilter',
                'condition_filter',
                executeIf: static function (ContainerInterface $c): bool {
                    throw new \RuntimeException('Simulated condition failure');
                }
            ),
        ];

        $targetResolver = new HookTargetResolver();
        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $result = apply_filters('condition_filter', 'keepme');

        $this->assertSame('keepme', $result, 'Filter must pass the value through when the condition fails');
        $this->assertSame(0, ExecuteIfFilterService::$instantiationCount);
    }

    public function testNonBoolExecuteIfResultSkipsHook(): void
    {
        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'condition_nonbool',
                executeIf: static fn (ContainerInterface $c) => 'yes'
            ),
        ];

        $targetResolver = new HookTargetResolver();
        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        do_action('condition_nonbool', 'hello');

        $this->assertSame(0, ExecuteIfActionService::$instantiationCount);
        $this->assertSame([], ExecuteIfActionService::$capturedValues);
    }

    public function testRuntimeRegistryRegistersExecuteIfGatedHooksButGatesAtFireTime(): void
    {
        $registry = new WPHooksRuntimeRegistry(new HookRuntimeResolver());
        $registry->registerHooksOn(new RuntimeExecuteIfService());

        $this->assertNotNull(
            $this->findRegisteredHook('action', 'runtime_executeIf_action'),
            'ExecuteIf-gated hook must be registered on runtime instances (gate evaluated at fire time)'
        );

        do_action('runtime_executeIf_action', 'hello');
        $this->assertSame([], RuntimeExecuteIfService::$captured);
    }

    public function testExecuteIfBindToTargetEnablesPrivateAccess(): void
    {
        // Static closure typed with the TARGET class but defined OUTSIDE it
        // (scopeClass = ExecuteIfHookTest). bindToTarget's plan-driven
        // scope-only bind gives it access to ExecuteIfActionService privates.
        $gate = static function (ExecuteIfActionService $target): bool {
            return $target->isPrivateEnabled();
        };

        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'bind_target_action',
                executeIf: $gate,
                executeIfParams: (new WPHookPlanProvider())->buildCallablePlan($gate)
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        do_action('bind_target_action', 'scoped');

        $this->assertSame(['scoped'], ExecuteIfActionService::$capturedValues);
    }

    public function testRegisterIfBindToTargetAtRegistrationAndDeferredActivation(): void
    {
        $gate = static function (ExecuteIfActionService $target): bool {
            return $target->isPrivateEnabled();
        };

        // Registration path: gate evaluated in registerAll (target class from
        // $registration->class), private access via scope bind.
        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'register_if_bound',
                executeIf: static fn (): bool => true,
                registerIf: $gate,
                registerIfParams: (new WPHookPlanProvider())->buildCallablePlan($gate)
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', 'register_if_bound'));

        do_action('register_if_bound', 'reg');
        $this->assertSame(['reg'], ExecuteIfActionService::$capturedValues);

        // Deferred path: gate re-evaluated at activation time (target class
        // from $data['key']->class inside gateDeferredActivation).
        ExecuteIfActionService::reset();

        $deferred = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'register_if_deferred_bound',
                deferRegister: true,
                executeIf: static fn (): bool => true,
                registerIf: $gate,
                registerIfParams: (new WPHookPlanProvider())->buildCallablePlan($gate)
            ),
        ];

        $deferredRegistry = $this->createRegistry($deferred, $this->container);
        $deferredRegistry->initialize();
        $deferredRegistry->activateDeferredByHook('register_if_deferred_bound');

        $this->assertNotNull($this->findRegisteredHook('action', 'register_if_deferred_bound'));

        do_action('register_if_deferred_bound', 'defer');
        $this->assertSame(['defer'], ExecuteIfActionService::$capturedValues);
    }

    public function testExecuteIfPlanIsPrecomputedByScanner(): void
    {
        $attr = new Action(
            hook: 'plan_test',
            executeIf: static function (ContainerInterface $c): bool {
                return true;
            }
        );

        $plan = (new WPHookPlanProvider())->buildCallablePlan($attr->executeIf);

        $this->assertTrue($plan['isStatic']);
        $this->assertSame(ExecuteIfHookTest::class, $plan['scopeClass']);
        $this->assertCount(1, $plan['params']);
        $this->assertSame('c', $plan['params'][0]['name']);
        $this->assertSame(ContainerInterface::class, $plan['params'][0]['type']);
        $this->assertFalse($plan['params'][0]['hasDefault']);
        $this->assertNull($plan['params'][0]['default']);

        // Builtin params carry no type but keep their default value.
        $builtin = (new WPHookPlanProvider())->buildCallablePlan(
            static fn (int $x = 5): bool => true
        );
        $this->assertTrue($builtin['isStatic']);
        $this->assertCount(1, $builtin['params']);
        $this->assertNull($builtin['params'][0]['type']);
        $this->assertTrue($builtin['params'][0]['hasDefault']);
        $this->assertSame(5, $builtin['params'][0]['default']);

        // No condition → empty plan.
        $empty = (new WPHookPlanProvider())->buildCallablePlan(null);
        $this->assertSame([], $empty['params']);
        $this->assertTrue($empty['isStatic']);
        $this->assertNull($empty['scopeClass']);
    }

    public function testExecuteIfPlanDrivenResolutionFiresAndSkipsHook(): void
    {
        // Plan-driven path (what the scanner exports to the cache): the
        // attribute pre-computes the plan, the registry consumes it
        // without any reflection at fire time.
        $attr = new Action(
            hook: 'plan_action',
            executeIf: static function (ContainerInterface $c): bool {
                return $c->has(ExecuteIfActionService::class);
            }
        );

        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'plan_action',
                executeIf: $attr->executeIf,
                executeIfParams: (new WPHookPlanProvider())->buildCallablePlan($attr->executeIf)
            ),
        ];
        
        $targetResolver = new HookTargetResolver();
        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        do_action('plan_action', 'via-plan');

        $this->assertSame(1, ExecuteIfActionService::$instantiationCount);
        $this->assertSame(['via-plan'], ExecuteIfActionService::$capturedValues);

        // Unresolvable plan entry (class not in container, no default) →
        // RuntimeException → logged, hook skipped — same contract as the
        // reflection fallback path.
        ExecuteIfActionService::reset();

        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'plan_bad',
                executeIf: static fn (): bool => true,
                executeIfParams: [
                    'isStatic' => true,
                    'scopeClass' => null,
                    'params' => [
                        ['name' => 'missing', 'type' => '\App\MissingService', 'hasDefault' => false, 'default' => null],
                    ],
                ]
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        do_action('plan_bad', 'hello');

        $this->assertSame(0, ExecuteIfActionService::$instantiationCount);
        $this->assertSame([], ExecuteIfActionService::$capturedValues);
    }

    /**
     * @return array<string,mixed>
     */
    private function action(
        string $class,
        string $method,
        string $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?Closure $executeIf = null,
        bool $deferRegister = false,
        array $executeIfParams = [],
        ?Closure $registerIf = null,
        array $registerIfParams = []
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
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function filter(
        string $class,
        string $method,
        string $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?Closure $executeIf = null,
        bool $deferRegister = false,
        array $executeIfParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'filter',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'defer_register' => $deferRegister,
            'execute_if' => $executeIf,
            'execute_if_params' => $executeIfParams,
        ];
    }
}
