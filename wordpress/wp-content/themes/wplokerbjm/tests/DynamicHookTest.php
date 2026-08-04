<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\Container;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksRuntimeRegistry;
use WPLokerBJM\Tests\Support\Fixtures\ConditionActionService;
use WPLokerBJM\Tests\Support\Fixtures\DynamicHookService;
use WPLokerBJM\Tests\Support\Fixtures\RuntimeDynamicService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Tests for closure-based dynamic hook names.
 *
 * The `hook` attribute argument may be a closure; its parameters are resolved
 * from the DI container at registration time (via WPHookPlanProvider), and the
 * closure result becomes the hook name. Covers plan-driven resolution,
 * reflection fallback, error handling, defer interplay, and runtime registry
 * skipping.
 */
class DynamicHookTest extends WplokerbjmTestCase
{
    private Container $container;

    private WPHookPlanProvider $planProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            DynamicHookService::class => \DI\autowire(),
            ConditionActionService::class => \DI\autowire(),
        ]);

        $this->container = $builder->build();
        $this->planProvider = new WPHookPlanProvider();

        DynamicHookService::reset();
        ConditionActionService::reset();
        RuntimeDynamicService::reset();
    }

    public function testClosureHookWithContainerParamRegistersAndFires(): void
    {
        $hook = static function (ContainerInterface $c): string {
            return 'dynamic_action';
        };

        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook),
        ], new WPHookPlanProvider());
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', 'dynamic_action'));

        do_action('dynamic_action', 'hello');

        self::assertSame(1, DynamicHookService::$instantiationCount);
        self::assertSame(['hello'], DynamicHookService::$capturedValues);
    }

    public function testStringHookIsUnchanged(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(DynamicHookService::class, 'onDynamicAction', 'plain_string_action'),
        ], new WPHookPlanProvider());
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', 'plain_string_action'));

        do_action('plain_string_action', 'plain');

        self::assertSame(['plain'], DynamicHookService::$capturedValues);
    }

    public function testNonStringClosureResultIsLoggedAndSkipped(): void
    {
        $hook = static fn (): int => 42;

        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook),
        ], new WPHookPlanProvider());
        $registry->initialize();

        // The registration was skipped — nothing was registered at all.
        self::assertSame([], $this->registeredHooks());

        do_action('42', 'nope');
        self::assertSame([], DynamicHookService::$capturedValues);
    }

    public function testUnresolvableClosureParamIsLoggedAndSkipped(): void
    {
        $hook = static function (self $service): string {
            return 'never_registered';
        };

        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook),
        ], new WPHookPlanProvider());
        $registry->initialize();

        self::assertSame([], $this->registeredHooks());

        do_action('never_registered', 'nope');
        self::assertSame([], DynamicHookService::$capturedValues);
    }

    public function testClosureHookComposesWithConditionGate(): void
    {
        $hook = static fn (): string => 'dynamic_combo';
        $condition = static function (ConditionActionService $service): bool {
            return $service !== null;
        };

        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                DynamicHookService::class,
                'onDynamicAction',
                $hook,
                condition: $condition,
            ),
        ], new WPHookPlanProvider());
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', 'dynamic_combo'));

        do_action('dynamic_combo', 'gated');

        self::assertSame(['gated'], DynamicHookService::$capturedValues);
    }

    public function testDeferredClosureHookActivatesViaResolvedName(): void
    {
        $hook = static fn (): string => 'dynamic_deferred';

        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook, defer: true),
        ], new WPHookPlanProvider());
        $registry->initialize();

        // Deferred — not registered yet.
        self::assertNull($this->findRegisteredHook('action', 'dynamic_deferred'));

        $registry->activateDeferredByHook('dynamic_deferred');

        self::assertNotNull($this->findRegisteredHook('action', 'dynamic_deferred'));

        do_action('dynamic_deferred', 'activated');

        self::assertSame(['activated'], DynamicHookService::$capturedValues);
    }

    public function testRuntimeRegistrySkipsClosureHooks(): void
    {
        $runtimeRegistry = new WPHooksRuntimeRegistry();
        $runtimeRegistry->registerHooksOn(new RuntimeDynamicService());

        // Closure hooks are unsupported on runtime-registered instances
        // (no container access) — the hook must be skipped with a warning.
        self::assertNull($this->findRegisteredHook('action', 'runtime_dynamic_action'));

        do_action('runtime_dynamic_action', 'nope');
        self::assertSame([], RuntimeDynamicService::$captured);
    }

    public function testZeroParamClosureHookWithStaticClassReference(): void
    {
        // No DI parameter needed — the closure resolves a static class
        // reference (self::class) at registration time, plain PHP.
        $hook = static fn (): string => self::class . '_boot';

        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook),
        ], new WPHookPlanProvider());
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', self::class . '_boot'));

        do_action(self::class . '_boot', 'static');

        self::assertSame(['static'], DynamicHookService::$capturedValues);
    }

    /**
     * Build a registration array for an action hook.
     *
     * @param string|\Closure $hook Static hook name or a closure resolving to one.
     * @param \Closure|null $condition Gate evaluated before the hook fires.
     * @param array<int, mixed> $conditionParams Pre-computed plan for the condition closure.
     * @param array<int, string> $tags Grouping tags for bulk activation/unregistration.
     */
    private function action(
        string $class,
        string $method,
        string|\Closure $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?Closure $condition = null,
        bool $defer = false,
        array $conditionParams = [],
        array $tags = [],
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
            'hook_params' => $hook instanceof Closure
                ? $this->planProvider->buildCallablePlan($hook)
                : [],
            'tags' => $tags,
        ];
    }
}
