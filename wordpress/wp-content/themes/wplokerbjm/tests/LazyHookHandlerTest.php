<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use DI\ContainerBuilder;
use DI\Container;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredHookManager;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\{WPHookPlanProvider, ContainerLazyHookHandler};
use WPLokerBJM\Tests\Support\Fixtures\FilterService;
use WPLokerBJM\Tests\Support\Fixtures\LazyHookService;
use WPLokerBJM\Tests\Support\Fixtures\MethodDeferredService;
use WPLokerBJM\Tests\Support\Fixtures\MethodMultiPriorityService;
use WPLokerBJM\Tests\Support\Fixtures\PrivateMethodService;
use WPLokerBJM\Tests\Support\Fixtures\ProtectedMethodService;
use WPLokerBJM\Tests\Support\Fixtures\ThrowingActionService;
use WPLokerBJM\Tests\Support\Fixtures\ThrowingFilterService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

class ContainerLazyHookHandlerTest extends WplokerbjmTestCase
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

        $this->registry = new WPHooksContainerRegistry($this->container, [], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));

        LazyHookService::reset();
        FilterService::reset();
        MethodDeferredService::reset();
        MethodMultiPriorityService::reset();
        ProtectedMethodService::reset();
    }

    private function seedRegistrations(array $registrations): void
    {
        $bind = \Closure::bind(
            static fn (WPHooksContainerRegistry $registry, array $hooksRegistration) => $registry->registerAll($hooksRegistration),
            null,
            WPHooksContainerRegistry::class
        );
        $bind($this->registry, $registrations);
    }

    public function testMethodActionProducesSideEffect(): void
    {
        $service = new LazyHookService();
        $this->container->set(LazyHookService::class, $service);

        $this->seedRegistrations([[
            'class' => LazyHookService::class,
            'method' => 'onAction',
            'type' => 'action',
            'hook' => 'lazy_action_hook',
            'priority' => 10,
            'accepted_args' => 1,
            'defer' => false,
            'target' => 'method',
            'visibility' => 'public',
        ]]);

        $this->registry->initialize();

        $this->assertNotNull(
            $this->findRegisteredHook('action', 'lazy_action_hook'),
        );

        do_action('lazy_action_hook', 'triggered');

        $this->assertNotEmpty(LazyHookService::$capturedValues);
        $this->assertSame(1, LazyHookService::$capturedValues[0]['id']);
        $this->assertSame('triggered', LazyHookService::$capturedValues[0]['value']);
    }

    public function testMethodFilterReturnsValue(): void
    {
        $service = new FilterService();
        $this->container->set(FilterService::class, $service);

        $this->seedRegistrations([[
            'class' => FilterService::class,
            'method' => 'onFilter',
            'type' => 'filter',
            'hook' => 'lazy_filter_hook',
            'priority' => 10,
            'accepted_args' => 2,
            'defer' => false,
            'target' => 'method',
            'visibility' => 'public',
        ]]);

        $this->registry->initialize();

        $result = apply_filters('lazy_filter_hook', 'hello', '_world');

        $this->assertSame('hello_world', $result);
        $this->assertNotEmpty(FilterService::$capturedArgs);
    }

    public function testMethodActionReturnsNullOnError(): void
    {
        $service = new ThrowingActionService();
        $this->container->set(ThrowingActionService::class, $service);

        $this->seedRegistrations([[
            'class' => ThrowingActionService::class,
            'method' => 'onAction',
            'type' => 'action',
            'hook' => 'throwing_action_hook',
            'priority' => 10,
            'accepted_args' => 1,
            'defer' => false,
            'target' => 'method',
            'visibility' => 'public',
        ]]);

        $this->registry->initialize();

        do_action('throwing_action_hook', 'test');

        $this->assertTrue(true, 'Action should not propagate exception');
    }

    public function testMethodFilterReturnsFirstArgOnError(): void
    {
        $service = new ThrowingFilterService();
        $this->container->set(ThrowingFilterService::class, $service);

        $this->seedRegistrations([[
            'class' => ThrowingFilterService::class,
            'method' => 'onFilter',
            'type' => 'filter',
            'hook' => 'throwing_filter_hook',
            'priority' => 10,
            'accepted_args' => 1,
            'defer' => false,
            'target' => 'method',
            'visibility' => 'public',
        ]]);

        $this->registry->initialize();

        $result = apply_filters('throwing_filter_hook', 'passthrough');

        $this->assertSame('passthrough', $result);
    }

    public function testDeferredMethodHook(): void
    {
        $service = new MethodDeferredService();
        $this->container->set(MethodDeferredService::class, $service);

        $this->seedRegistrations([[
            'class' => MethodDeferredService::class,
            'method' => 'onDeferredAction',
            'type' => 'action',
            'hook' => 'deferred_method_action',
            'priority' => 10,
            'accepted_args' => 1,
            'defer' => true,
            'target' => 'method',
            'visibility' => 'public',
        ]]);

        $this->registry->initialize();

        $this->assertNull(
            $this->findRegisteredHook('action', 'deferred_method_action'),
        );

        $this->registry->activateDeferredByCallable(
            [$this->container->get(MethodDeferredService::class), 'onDeferredAction'],
        );

        $this->assertNotNull(
            $this->findRegisteredHook('action', 'deferred_method_action'),
        );

        do_action('deferred_method_action', 'later');

        $this->assertSame(['later'], MethodDeferredService::$captured);
    }

    public function testClassNotInContainerIsSkipped(): void
    {
        $this->seedRegistrations([[
            'class' => 'NonExistent\\Class',
            'method' => 'someMethod',
            'type' => 'filter',
            'hook' => 'ghost_hook',
            'priority' => 10,
            'accepted_args' => 1,
            'defer' => false,
            'target' => 'method',
            'visibility' => 'public',
        ]]);

        $this->registry->initialize();

        $this->assertNull($this->findRegisteredHook('filter', 'ghost_hook'));
    }

    public function testMethodHookUsesContainerLazyHookHandlerInstance(): void
    {
        $service = new LazyHookService();
        $this->container->set(LazyHookService::class, $service);

        $this->seedRegistrations([[
            'class' => LazyHookService::class,
            'method' => 'onAction',
            'type' => 'action',
            'hook' => 'handler_type_check',
            'priority' => 10,
            'accepted_args' => 1,
            'defer' => false,
            'target' => 'method',
            'visibility' => 'public',
        ]]);

        $this->registry->initialize();

        $hook = $this->findRegisteredHook('action', 'handler_type_check');
        $this->assertNotNull($hook);

        $handler = $hook['callable'];
        $this->assertInstanceOf(ContainerLazyHookHandler::class, $handler);
        $this->assertStringContainsString('onAction', $handler->label);
    }

    public function testMultiplePriorities(): void
    {
        $service = new MethodMultiPriorityService();
        $this->container->set(MethodMultiPriorityService::class, $service);

        $this->seedRegistrations([
            [
                'class' => MethodMultiPriorityService::class,
                'method' => 'onFilter',
                'type' => 'filter',
                'hook' => 'multi_priority_method_filter',
                'priority' => 10,
                'accepted_args' => 1,
                'defer' => false,
                'target' => 'method',
                'visibility' => 'public',
            ],
            [
                'class' => MethodMultiPriorityService::class,
                'method' => 'onFilter',
                'type' => 'filter',
                'hook' => 'multi_priority_method_filter',
                'priority' => 20,
                'accepted_args' => 1,
                'defer' => false,
                'target' => 'method',
                'visibility' => 'public',
            ],
        ]);

        $this->registry->initialize();

        $hooks = $this->registeredHooks();
        $matched = array_values(array_filter(
            $hooks,
            fn(array $h): bool =>
                $h['hook'] === 'multi_priority_method_filter' && $h['type'] === 'filter',
        ));

        $this->assertCount(2, $matched);
        $this->assertSame(10, $matched[0]['priority']);
        $this->assertSame(20, $matched[1]['priority']);

        $result = apply_filters('multi_priority_method_filter', 'val');
        $this->assertSame('val_processed_processed', $result);
    }

    public function testProtectedMethodHook(): void
    {
        $service = new ProtectedMethodService();
        $this->container->set(ProtectedMethodService::class, $service);

        $this->seedRegistrations([[
            'class' => ProtectedMethodService::class,
            'method' => 'onProtectedAction',
            'type' => 'action',
            'hook' => 'protected_method_action',
            'priority' => 10,
            'accepted_args' => 0,
            'defer' => false,
            'target' => 'method',
            'visibility' => 'protected',
        ]]);

        $this->registry->initialize();

        $this->assertFalse(ProtectedMethodService::$called);

        do_action('protected_method_action');

        $this->assertTrue(ProtectedMethodService::$called);
    }

    public function testPrivateMethodHook(): void
    {
        $service = new PrivateMethodService();
        $this->container->set(PrivateMethodService::class, $service);

        $this->seedRegistrations([[
            'class' => PrivateMethodService::class,
            'method' => 'onPrivateFilter',
            'type' => 'filter',
            'hook' => 'private_method_filter',
            'priority' => 10,
            'accepted_args' => 1,
            'defer' => false,
            'target' => 'method',
            'visibility' => 'private',
        ]]);

        $this->registry->initialize();

        $result = apply_filters('private_method_filter', 'hello');

        $this->assertSame('hello_private', $result);
    }
}
