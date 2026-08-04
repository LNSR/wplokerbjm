<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\ContainerBuilder;
use DI\Container;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, WPHooksContainerRegistry, WPHooksRuntimeRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\{WPHookPlanProvider};
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

        $registry = new WPHooksContainerRegistry($this->container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
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

        $registry = new WPHooksContainerRegistry($this->container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
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
                defer: true,
                executeIf: static fn (ContainerInterface $c): bool => false
            ),
        ];

        $registry = new WPHooksContainerRegistry($this->container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
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

        $registry = new WPHooksContainerRegistry($this->container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
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

        $registry = new WPHooksContainerRegistry($this->container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        do_action('condition_nonbool', 'hello');

        $this->assertSame(0, ExecuteIfActionService::$instantiationCount);
        $this->assertSame([], ExecuteIfActionService::$capturedValues);
    }

    public function testRuntimeRegistrySkipsExecuteIfGatedHooks(): void
    {
        $registry = new WPHooksRuntimeRegistry();
        $registry->registerHooksOn(new RuntimeExecuteIfService());

        $this->assertNull(
            $this->findRegisteredHook('action', 'runtime_executeIf_action'),
            'ExecuteIf-gated hook must NOT be registered on runtime instances'
        );

        do_action('runtime_executeIf_action', 'hello');
        $this->assertSame([], RuntimeExecuteIfService::$captured);
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

        $this->assertCount(1, $plan);
        $this->assertSame('c', $plan[0]['name']);
        $this->assertSame(ContainerInterface::class, $plan[0]['type']);
        $this->assertFalse($plan[0]['hasDefault']);
        $this->assertNull($plan[0]['default']);

        // Builtin params carry no type but keep their default value.
        $builtin = (new WPHookPlanProvider())->buildCallablePlan(
            static fn (int $x = 5): bool => true
        );
        $this->assertCount(1, $builtin);
        $this->assertNull($builtin[0]['type']);
        $this->assertTrue($builtin[0]['hasDefault']);
        $this->assertSame(5, $builtin[0]['default']);

        // No condition → empty plan.
        $this->assertSame([], (new WPHookPlanProvider())->buildCallablePlan(null));
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

        $registry = new WPHooksContainerRegistry($this->container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
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
                    ['name' => 'missing', 'type' => '\App\MissingService', 'hasDefault' => false, 'default' => null],
                ]
            ),
        ];

        $registry = new WPHooksContainerRegistry($this->container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
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
        bool $defer = false,
        array $executeIfParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'defer' => $defer,
            'execute_if' => $executeIf,
            'execute_if_params' => $executeIfParams,
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
        bool $defer = false,
        array $executeIfParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'filter',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'defer' => $defer,
            'execute_if' => $executeIf,
            'execute_if_params' => $executeIfParams,
        ];
    }
}
