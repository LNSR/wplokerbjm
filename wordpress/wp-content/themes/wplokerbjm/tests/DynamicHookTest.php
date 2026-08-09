<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\Container;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, WPHooksContainerRegistry, WPHooksRuntimeRegistry, HookTargetResolver};
use WPLokerBJM\Tests\Support\Fixtures\ExecuteIfActionService;
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
            ExecuteIfActionService::class => \DI\autowire(),
        ]);

        $this->container = $builder->build();
        $this->planProvider = new WPHookPlanProvider();

        DynamicHookService::reset();
        ExecuteIfActionService::reset();
        RuntimeDynamicService::reset();
    }

    public function testClosureHookWithContainerParamRegistersAndFires(): void
    {
        $hook = static function (ContainerInterface $c): string {
            return 'dynamic_action';
        };
        $targetResolver = new HookTargetResolver();

        $registry = $this->createRegistry([
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook),
        ], $this->container);
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', 'dynamic_action'));

        do_action('dynamic_action', 'hello');

        self::assertSame(1, DynamicHookService::$instantiationCount);
        self::assertSame(['hello'], DynamicHookService::$capturedValues);
    }

    public function testStringHookIsUnchanged(): void
    {
        $targetResolver = new HookTargetResolver();

        $registry = $this->createRegistry([
            $this->action(DynamicHookService::class, 'onDynamicAction', 'plain_string_action'),
        ], $this->container);
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', 'plain_string_action'));

        do_action('plain_string_action', 'plain');

        self::assertSame(['plain'], DynamicHookService::$capturedValues);
    }

    public function testNonStringClosureResultIsLoggedAndSkipped(): void
    {
        $hook = static fn (): int => 42;
        $targetResolver = new HookTargetResolver();

        $registry = $this->createRegistry([
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook),
        ], $this->container);
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
        $targetResolver = new HookTargetResolver();

        $registry = $this->createRegistry([
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook),
        ], $this->container);
        $registry->initialize();

        self::assertSame([], $this->registeredHooks());

        do_action('never_registered', 'nope');
        self::assertSame([], DynamicHookService::$capturedValues);
    }

    public function testClosureHookComposesWithConditionGate(): void
    {
        $hook = static fn (): string => 'dynamic_combo';
        $executeIf = static function (ExecuteIfActionService $service): bool {
            return $service !== null;
        };
        $targetResolver = new HookTargetResolver();

        $registry = $this->createRegistry([
            $this->action(
                DynamicHookService::class,
                'onDynamicAction',
                $hook,
                executeIf: $executeIf,
            ),
        ], $this->container);
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', 'dynamic_combo'));

        do_action('dynamic_combo', 'gated');

        self::assertSame(['gated'], DynamicHookService::$capturedValues);
    }

    public function testDeferredClosureHookActivatesViaResolvedName(): void
    {
        $hook = static fn (): string => 'dynamic_deferred';

        $targetResolver = new HookTargetResolver();
        $registry = $this->createRegistry([
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook, deferRegister: true),
        ], $this->container);
        $registry->initialize();

        // Deferred — not registered yet.
        self::assertNull($this->findRegisteredHook('action', 'dynamic_deferred'));

        $registry->activateDeferredByHook('dynamic_deferred');

        self::assertNotNull($this->findRegisteredHook('action', 'dynamic_deferred'));

        do_action('dynamic_deferred', 'activated');

        self::assertSame(['activated'], DynamicHookService::$capturedValues);
    }

    public function testRuntimeRegistryRegistersClosureHooks(): void
    {
        $runtimeRegistry = new WPHooksRuntimeRegistry();

        // The instance must be kept alive — runtime hooks are instance-lifetime
        // scoped (weak references), so an unreferenced instance dies and its
        // hooks nuke themselves.
        $service = new RuntimeDynamicService();
        $runtimeRegistry->registerHooksOn($service);

        // Closure hooks are supported on runtime-registered instances — the
        // static attribute closure is invoked directly (scope = declaring class).
        self::assertNotNull($this->findRegisteredHook('action', 'runtime_dynamic_action'));

        do_action('runtime_dynamic_action', 'fired');
        self::assertSame(['fired'], RuntimeDynamicService::$captured);
    }

    public function testZeroParamClosureHookWithStaticClassReference(): void
    {
        // No DI parameter needed — the closure resolves a static class
        // reference (self::class) at registration time, plain PHP.
        $hook = static fn (): string => self::class . '_boot';

        $registry = $this->createRegistry([
            $this->action(DynamicHookService::class, 'onDynamicAction', $hook),
        ], $this->container);
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', self::class . '_boot'));

        do_action(self::class . '_boot', 'static');

        self::assertSame(['static'], DynamicHookService::$capturedValues);
    }

    /**
     * Build a registration array for an action hook.
     *
     * @param string|\Closure $hook Static hook name or a closure resolving to one.
     * @param \Closure|null $executeIf Gate evaluated before the hook fires.
     * @param array<int, mixed> $executeIfParams Pre-computed plan for the condition closure.
     * @param array<int, string> $tags Grouping tags for bulk activation/unregistration.
     */
    private function action(
        string $class,
        string $method,
        string|\Closure $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?Closure $executeIf = null,
        bool $deferRegister = false,
        array $executeIfParams = [],
        array $tags = [],
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
            'hook_params' => $hook instanceof Closure
                ? $this->planProvider->buildCallablePlan($hook)
                : [],
            'tags' => $tags,
        ];
    }
}
