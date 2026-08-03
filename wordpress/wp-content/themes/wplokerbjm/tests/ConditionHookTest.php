<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\ContainerBuilder;
use DI\Container;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\{WPHookPlanProvider, WPHooksRegistry, WPHooksRuntimeRegistry};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Tests\Support\Fixtures\ConditionActionService;
use WPLokerBJM\Tests\Support\Fixtures\ConditionFilterService;
use WPLokerBJM\Tests\Support\Fixtures\RuntimeConditionService;

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
class ConditionHookTest extends WplokerbjmTestCase
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
            ConditionActionService::class => \DI\autowire(),
            ConditionFilterService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();

        ConditionActionService::reset();
        ConditionFilterService::reset();
        RuntimeConditionService::reset();
    }

    public function testConditionReceivesContainerAndFiresHook(): void
    {
        $registrations = [
            $this->action(
                ConditionActionService::class,
                'onConditionAction',
                'condition_action',
                condition: static function (ContainerInterface $c): bool {
                    // The gate must receive the DI container and be able
                    // to query it for services.
                    return $c->has(ConditionActionService::class);
                }
            ),
        ];

        $registry = new WPHooksRegistry($this->container, $registrations, new WPHookPlanProvider());
        $registry->initialize();

        do_action('condition_action', 'hello');

        $this->assertSame(1, ConditionActionService::$instantiationCount);
        $this->assertSame(['hello'], ConditionActionService::$capturedValues);
    }

    public function testConditionFalseSkipsHookWithoutResolvingService(): void
    {
        $registrations = [
            $this->action(
                ConditionActionService::class,
                'onConditionAction',
                'condition_action',
                condition: static fn (ContainerInterface $c): bool => false
            ),
        ];

        $registry = new WPHooksRegistry($this->container, $registrations, new WPHookPlanProvider());
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', 'condition_action'));

        do_action('condition_action', 'hello');

        $this->assertSame(0, ConditionActionService::$instantiationCount);
        $this->assertSame([], ConditionActionService::$capturedValues);
    }

    public function testConditionFalseSkipsDeferredHookEvenAfterActivation(): void
    {
        $registrations = [
            $this->action(
                ConditionActionService::class,
                'onConditionAction',
                'condition_deferred',
                defer: true,
                condition: static fn (ContainerInterface $c): bool => false
            ),
        ];

        $registry = new WPHooksRegistry($this->container, $registrations, new WPHookPlanProvider());
        $registry->initialize();

        // Deferred: not registered during initialize()...
        $this->assertNull($this->findRegisteredHook('action', 'condition_deferred'));

        // ...activated on demand, but the condition gate still applies.
        $registry->activateDeferredByHook('condition_deferred');
        $this->assertNotNull($this->findRegisteredHook('action', 'condition_deferred'));

        do_action('condition_deferred', 'hello');

        $this->assertSame(0, ConditionActionService::$instantiationCount);
        $this->assertSame([], ConditionActionService::$capturedValues);
    }

    public function testConditionExceptionLogsErrorAndPassesFilterThrough(): void
    {
        $registrations = [
            $this->filter(
                ConditionFilterService::class,
                'onConditionFilter',
                'condition_filter',
                condition: static function (ContainerInterface $c): bool {
                    throw new \RuntimeException('Simulated condition failure');
                }
            ),
        ];

        $registry = new WPHooksRegistry($this->container, $registrations, new WPHookPlanProvider());
        $registry->initialize();

        $result = apply_filters('condition_filter', 'keepme');

        $this->assertSame('keepme', $result, 'Filter must pass the value through when the condition fails');
        $this->assertSame(0, ConditionFilterService::$instantiationCount);
    }

    public function testNonBoolConditionResultSkipsHook(): void
    {
        $registrations = [
            $this->action(
                ConditionActionService::class,
                'onConditionAction',
                'condition_nonbool',
                condition: static fn (ContainerInterface $c) => 'yes'
            ),
        ];

        $registry = new WPHooksRegistry($this->container, $registrations, new WPHookPlanProvider());
        $registry->initialize();

        do_action('condition_nonbool', 'hello');

        $this->assertSame(0, ConditionActionService::$instantiationCount);
        $this->assertSame([], ConditionActionService::$capturedValues);
    }

    public function testRuntimeRegistrySkipsConditionGatedHooks(): void
    {
        $registry = new WPHooksRuntimeRegistry();
        $registry->registerHooksOn(new RuntimeConditionService());

        $this->assertNull(
            $this->findRegisteredHook('action', 'runtime_condition_action'),
            'Condition-gated hook must NOT be registered on runtime instances'
        );

        do_action('runtime_condition_action', 'hello');
        $this->assertSame([], RuntimeConditionService::$captured);
    }

    public function testConditionPlanIsPrecomputedByScanner(): void
    {
        $attr = new Action(
            hook: 'plan_test',
            condition: static function (ContainerInterface $c): bool {
                return true;
            }
        );

        $plan = (new WPHookPlanProvider())->buildCallablePlan($attr->condition);

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

    public function testConditionPlanDrivenResolutionFiresAndSkipsHook(): void
    {
        // Plan-driven path (what the scanner exports to the cache): the
        // attribute pre-computes the plan, the registry consumes it
        // without any reflection at fire time.
        $attr = new Action(
            hook: 'plan_action',
            condition: static function (ContainerInterface $c): bool {
                return $c->has(ConditionActionService::class);
            }
        );

        $registrations = [
            $this->action(
                ConditionActionService::class,
                'onConditionAction',
                'plan_action',
                condition: $attr->condition,
                conditionParams: (new WPHookPlanProvider())->buildCallablePlan($attr->condition)
            ),
        ];

        $registry = new WPHooksRegistry($this->container, $registrations, new WPHookPlanProvider());
        $registry->initialize();

        do_action('plan_action', 'via-plan');

        $this->assertSame(1, ConditionActionService::$instantiationCount);
        $this->assertSame(['via-plan'], ConditionActionService::$capturedValues);

        // Unresolvable plan entry (class not in container, no default) →
        // RuntimeException → logged, hook skipped — same contract as the
        // reflection fallback path.
        ConditionActionService::reset();

        $registrations = [
            $this->action(
                ConditionActionService::class,
                'onConditionAction',
                'plan_bad',
                condition: static fn (): bool => true,
                conditionParams: [
                    ['name' => 'missing', 'type' => '\App\MissingService', 'hasDefault' => false, 'default' => null],
                ]
            ),
        ];

        $registry = new WPHooksRegistry($this->container, $registrations, new WPHookPlanProvider());
        $registry->initialize();

        do_action('plan_bad', 'hello');

        $this->assertSame(0, ConditionActionService::$instantiationCount);
        $this->assertSame([], ConditionActionService::$capturedValues);
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
        ?Closure $condition = null,
        bool $defer = false,
        array $conditionParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'defer' => $defer,
            'condition' => $condition,
            'condition_params' => $conditionParams,
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
        ?Closure $condition = null,
        bool $defer = false,
        array $conditionParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'filter',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'defer' => $defer,
            'condition' => $condition,
            'condition_params' => $conditionParams,
        ];
    }
}
