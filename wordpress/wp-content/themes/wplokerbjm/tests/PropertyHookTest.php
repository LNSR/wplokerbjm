<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use DI\ContainerBuilder;
use DI\Container;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\ContainerLazyPropertyHookHandler;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, HookTargetResolver, WPHooksContainerRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Tests\Support\Fixtures\PropertyActionService;
use WPLokerBJM\Tests\Support\Fixtures\PropertyDeferredService;
use WPLokerBJM\Tests\Support\Fixtures\PropertyFilterService;
use WPLokerBJM\Tests\Support\Fixtures\PropertyMultiPriorityService;
use WPLokerBJM\Tests\Support\Fixtures\PropertyNonClosureService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};


/**
 * Test suite for property-closure hooks via #[Action]/#[Filter] attributes.
 *
 * Verifies that:
 *  - ContainerLazyPropertyHookHandler reads a public property and invokes the closure.
 *  - Filter closures return values correctly (apply_filters).
 *  - Action closures produce side effects (do_action).
 *  - Multiple #[Filter] on the same property (IS_REPEATABLE) work.
 *  - Deferred property hooks are activated on demand.
 *  - Non-Closure properties are handled gracefully (error logged, fallback returned).
 *  - Container missing the class is skipped without crash.
 */
class PropertyHookTest extends WplokerbjmTestCase
{
    private Container $container;
    private WPHooksContainerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $this->container = $builder->build();
        $resolverTarget = new HookTargetResolver();
        $this->registry = $this->createRegistry([], $this->container);

        // Reset fixture static state
        PropertyFilterService::reset();
        PropertyActionService::reset();
        PropertyMultiPriorityService::reset();
        PropertyDeferredService::reset();
    }

    /**
     * Seed registrations via a bound closure (bypassing the container check),
     * then verify internal state.
     *
     * @param array<int, array{class: string, method: string, type: 'action'|'filter', hook: string, priority: int, accepted_args: int, deferRegister: bool}> $registrations
     */
    private function seedRegistrations(array $registrations): void
    {
        $bind = \Closure::bind(
            static fn (WPHooksContainerRegistry $registry, array $hooksRegistration) => $registry->registerAll($hooksRegistration),
            null,
            WPHooksContainerRegistry::class
        );
        $bind($this->registry, $registrations);
    }

    // ── Filter tests ─────────────────────────────────────────────────

    public function testPropertyFilterInvokesClosure(): void
    {
        $service = new PropertyFilterService();
        $this->container->set(PropertyFilterService::class, $service);

        $this->seedRegistrations([
            [
                'class'         => PropertyFilterService::class,
                'method'        => 'appendSuffix',
                'type'          => 'filter',
                'hook'          => 'property_filter',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer_register' => false,
                'target'        => 'property',
            ],
        ]);

        $this->registry->initialize();

        $this->assertNotNull(
            $this->findRegisteredHook('filter', 'property_filter'),
            'Property filter hook should be registered',
        );

        $result = apply_filters('property_filter', 'hello');

        $this->assertSame('hello_suffixed', $result, 'Filter closure should modify the value');
        $this->assertSame(['hello'], PropertyFilterService::$capturedValues, 'Closure should have been invoked');
    }

    public function testPropertyActionProducesSideEffect(): void
    {
        $service = new PropertyActionService();
        $this->container->set(PropertyActionService::class, $service);

        $this->seedRegistrations([
            [
                'class'         => PropertyActionService::class,
                'method'        => 'logAction',
                'type'          => 'action',
                'hook'          => 'property_action',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer_register' => false,
                'target'        => 'property',
            ],
        ]);

        $this->registry->initialize();

        do_action('property_action', 'triggered');

        $this->assertSame(['triggered'], PropertyActionService::$capturedValues, 'Action closure should have been triggered');
    }

    public function testPropertyFilterReturnsValueThroughApplyFilters(): void
    {
        $service = new PropertyFilterService();
        $this->container->set(PropertyFilterService::class, $service);

        $this->seedRegistrations([
            [
                'class'         => PropertyFilterService::class,
                'method'        => 'appendSuffix',
                'type'          => 'filter',
                'hook'          => 'property_filter',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer_register' => false,
                'target'        => 'property',
            ],
        ]);

        $this->registry->initialize();

        $result = apply_filters('property_filter', 'input');
        $this->assertSame('input_suffixed', $result);
    }

    // ── Multiple priorities on same property ─────────────────────────

    public function testMultiplePrioritiesOnSameProperty(): void
    {
        $service = new PropertyMultiPriorityService();
        $this->container->set(PropertyMultiPriorityService::class, $service);

        $this->seedRegistrations([
            [
                'class'         => PropertyMultiPriorityService::class,
                'method'        => 'multiFilter',
                'type'          => 'filter',
                'hook'          => 'multi_priority_filter',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer_register' => false,
                'target'        => 'property',
            ],
            [
                'class'         => PropertyMultiPriorityService::class,
                'method'        => 'multiFilter',
                'type'          => 'filter',
                'hook'          => 'multi_priority_filter',
                'priority'      => 20,
                'accepted_args' => 1,
                'defer_register' => false,
                'target'        => 'property',
            ],
        ]);

        $this->registry->initialize();

        $hooks = $this->registeredHooks();
        $matched = array_values(array_filter(
            $hooks,
            fn(array $h): bool => $h['hook'] === 'multi_priority_filter' && $h['type'] === 'filter',
        ));

        $this->assertCount(2, $matched, 'Both priority registrations should be active');
        $this->assertSame(10, $matched[0]['priority']);
        $this->assertSame(20, $matched[1]['priority']);

        $result = apply_filters('multi_priority_filter', 'val');
        $this->assertSame('val_processed_processed', $result, 'Both priority filters should apply, chaining the value');
    }

    // ── Deferred property hooks ──────────────────────────────────────

    public function testDeferredPropertyHookNotRegisteredOnInitialize(): void
    {
        $service = new PropertyDeferredService();
        $this->container->set(PropertyDeferredService::class, $service);

        $this->seedRegistrations([
            [
                'class'         => PropertyDeferredService::class,
                'method'        => 'deferredFilter',
                'type'          => 'filter',
                'hook'          => 'deferred_property_filter',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer_register' => true,
                'target'        => 'property',
            ],
        ]);

        $this->registry->initialize();

        $this->assertNull(
            $this->findRegisteredHook('filter', 'deferred_property_filter'),
            'Deferred property hook should NOT be registered after initialize()',
        );

        // Activate by method name (which also works for property names)
        $this->registry->activateDeferredByCallable([$this->container->get(PropertyDeferredService::class), 'deferredFilter']);

        $this->assertNotNull(
            $this->findRegisteredHook('filter', 'deferred_property_filter'),
            'Deferred property hook should be registered after activateDeferredByMethod',
        );

        $result = apply_filters('deferred_property_filter', 'later');
        $this->assertSame('later_deferred', $result, 'Deferred property closure should execute and modify value');
    }

    public function testNonClosurePropertyLogsErrorAndReturnsFallback(): void
    {
        $service = new PropertyNonClosureService();
        $this->container->set(PropertyNonClosureService::class, $service);

        $this->seedRegistrations([
            [
                'class'         => PropertyNonClosureService::class,
                'method'        => 'notAClosure',
                'type'          => 'filter',
                'hook'          => 'non_closure_filter',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer_register' => false,
                'target'        => 'property',
            ],
        ]);

        $this->registry->initialize();

        // Should not throw — ContainerLazyPropertyHookHandler catches the error
        $result = apply_filters('non_closure_filter', 'fallback_test');

        // Should return the first argument as fallback
        $this->assertSame('fallback_test', $result, 'Non-closure property should return fallback first arg');
    }

    // ── Edge cases ───────────────────────────────────────────────────

    public function testClassNotInContainerIsSkipped(): void
    {
        // Register a property hook for a class NOT in the container
        $this->seedRegistrations([
            [
                'class'         => 'NonExistent\\Class',
                'method'        => 'someProp',
                'type'          => 'filter',
                'hook'          => 'ghost_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer_register' => false,
                'target'        => 'property',
            ],
        ]);

        $this->registry->initialize();

        $this->assertNull(
            $this->findRegisteredHook('filter', 'ghost_hook'),
            'Hook for class not in container should be skipped',
        );
    }

    public function testPropertyHookUsesContainerLazyPropertyHookHandlerInstance(): void
    {
        $service = new PropertyFilterService();
        $this->container->set(PropertyFilterService::class, $service);

        $this->seedRegistrations([
            [
                'class'         => PropertyFilterService::class,
                'method'        => 'appendSuffix',
                'type'          => 'filter',
                'hook'          => 'handler_type_check',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer_register' => false,
                'target'        => 'property',
            ],
        ]);

        $this->registry->initialize();

        $hook = $this->findRegisteredHook('filter', 'handler_type_check');
        $this->assertNotNull($hook, 'Handler should be registered');

        $handler = $hook['callable'];
        $this->assertInstanceOf(ContainerLazyPropertyHookHandler::class, $handler, 'Property hooks should use ContainerLazyPropertyHookHandler');
        $this->assertStringContainsString(
            'appendSuffix',
            $handler->label,
            'Label should contain the property name',
        );
    }

    public function testStaticClosureDoesNotCaptureThis(): void
    {
        $service = new class {
            public static array $captured = [];

            #[Filter(hook: 'static_closure_filter', priority: 10, acceptedArgs: 1)]
            public $staticClosure = static function (string $value): string {
                self::$captured[] = $value;
                return $value . '_static';
            };
        };

        $className = get_class($service);
        $this->container->set($className, $service);

        $this->seedRegistrations([
            [
                'class'         => $className,
                'method'        => 'staticClosure',
                'type'          => 'filter',
                'hook'          => 'static_closure_filter',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer_register' => false,
                'target'        => 'property',
            ],
        ]);

        $this->registry->initialize();

        $result = apply_filters('static_closure_filter', 'static_test');
        $this->assertSame('static_test_static', $result, 'Static closure on property should work');
    }

    // ── Property hook args (executeIf name-matching) ─────────────────

    public function testPropertyHookExecuteIfResolvesHookArgsByName(): void
    {
        $service = new PropertyFilterService();
        $this->container->set(PropertyFilterService::class, $service);

        $gate = static function (string $value): bool {
            return $value === 'go';
        };
        $plan = (new WPHookPlanProvider())->buildCallablePlan($gate);

        $this->seedRegistrations([
            [
                'class'             => PropertyFilterService::class,
                'method'            => 'appendSuffix',
                'type'              => 'filter',
                'hook'              => 'property_execute_hook',
                'priority'          => 10,
                'accepted_args'     => 1,
                'defer_register'    => false,
                'target'            => 'property',
                'execute_if'        => $gate,
                'execute_if_params' => $plan,
                'hook_args'         => ['value'],
            ],
        ]);

        $this->registry->initialize();

        // Gate name-matches 'go' into $value → passes → the callable runs.
        $this->assertSame('go_suffixed', apply_filters('property_execute_hook', 'go'));

        // Gate false → the filter passes the original value through untouched.
        $this->assertSame('no', apply_filters('property_execute_hook', 'no'));
    }
}
