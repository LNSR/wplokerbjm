<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Attributes\Filter;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{RuntimeInstanceHookHandler, RuntimeCallableHookHandler, RuntimeInstancePropertyHookHandler};
use DI\ContainerBuilder;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{HookRuntimeResolver, WPHooksRuntimeCache, WPHooksRuntimeRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\AnonClassHookMetadata;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

class RuntimeRegistryTest extends WplokerbjmTestCase
{
    private WPHooksRuntimeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->container()->make(WPHooksRuntimeRegistry::class, [
            'provider' => null,
        ]);

        // Extend hook mocks with remove_action / remove_filter support
        // so unregister actually strips entries from the registered-hooks array.
        $hooks = &$GLOBALS['__wplokerbjm_registered_hooks'];

        \Brain\Monkey\Functions\when('remove_action')->alias(
            function (string $hook, $callable, int $priority = 10) use (&$hooks) {
                $hooks = array_values(array_filter(
                    $hooks,
                    fn(array $h): bool =>
                    !($h['type'] === 'action' && $h['hook'] === $hook
                        && $h['callable'] === $callable && $h['priority'] === $priority),
                ));
            },
        );

        \Brain\Monkey\Functions\when('remove_filter')->alias(
            function (string $hook, $callable, int $priority = 10) use (&$hooks) {
                $hooks = array_values(array_filter(
                    $hooks,
                    fn(array $h): bool =>
                    !($h['type'] === 'filter' && $h['hook'] === $hook
                        && $h['callable'] === $callable && $h['priority'] === $priority),
                ));
            },
        );
    }

    // ── Basic registration ────────────────────────────────────────────

    public function testActionHookRegisteredAndFired(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'runtime_action_test', acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val;
            }
        };

        $this->registry->registerHooksOn($anon);

        $registered = $this->findRegisteredHook('action', 'runtime_action_test');
        $this->assertNotNull($registered);
        $this->assertInstanceOf(RuntimeInstanceHookHandler::class, $registered['callable']);

        do_action('runtime_action_test', 'fired');

        $this->assertSame(['fired'], $captured);
    }

    public function testFilterHookRegisteredAndFired(): void
    {
        $anon = new class {
            #[Filter(hook: 'runtime_filter_test', acceptedArgs: 1)]
            public function transform(string $value): string
            {
                return $value . '_transformed';
            }
        };

        $this->registry->registerHooksOn($anon);

        $result = apply_filters('runtime_filter_test', 'input');

        $this->assertSame('input_transformed', $result);
    }

    // ── Multiple hooks on one instance ─────────────────────────────────

    public function testMultipleHooksOnSameInstance(): void
    {
        $actions = [];
        $filters = [];

        $anon = new class ($actions, $filters) {
            public function __construct(
            private array &$actions,
            private array &$filters,
            ) {}

            #[Action(hook: 'multi_action_a', acceptedArgs: 1)]
            public function onActionA(string $v): void
            {
                $this->actions[] = "A:$v"; }

            #[Action(hook: 'multi_action_b', acceptedArgs: 1)]
            public function onActionB(string $v): void
            {
                $this->actions[] = "B:$v"; }

            #[Filter(hook: 'multi_filter_x', acceptedArgs: 1)]
            public function onFilterX(string $v): string
            {
                $this->filters[] = "X:$v";
                return $v . 'x'; }

            #[Filter(hook: 'multi_filter_y', acceptedArgs: 1)]
            public function onFilterY(string $v): string
            {
                $this->filters[] = "Y:$v";
                return $v . 'y'; }
        };

        $this->registry->registerHooksOn($anon);

        do_action('multi_action_a', 'h1');
        do_action('multi_action_b', 'h2');

        $fx = apply_filters('multi_filter_x', 'in');
        $fy = apply_filters('multi_filter_y', 'in');

        $this->assertCount(2, $actions);
        $this->assertSame('A:h1', $actions[0]);
        $this->assertSame('B:h2', $actions[1]);
        $this->assertSame('inx', $fx);
        $this->assertSame('iny', $fy);
    }

    // ── Visibility ─────────────────────────────────────────────────────

    public function testProtectedMethodHook(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_protected_action', acceptedArgs: 0)]
            protected function onProtectedAction(): void
            {
                $this->captured[] = 'protected_called';
            }
        };

        $this->registry->registerHooksOn($anon);
        do_action('rt_protected_action');

        $this->assertSame(['protected_called'], $captured);
    }

    public function testPrivateMethodHook(): void
    {
        $anon = new class {
            #[Filter(hook: 'rt_private_filter', acceptedArgs: 1)]
            private function onPrivateFilter(string $value): string
            {
                return $value . '_private_suffix';
            }
        };

        $this->registry->registerHooksOn($anon);

        $result = apply_filters('rt_private_filter', 'hello');

        $this->assertSame('hello_private_suffix', $result);
    }

    // ── Unregistration ─────────────────────────────────────────────────

    public function testUnregisterRemovesHooks(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_unreg_action', acceptedArgs: 1)]
            public function onAction(string $v): void
            {
                $this->captured[] = $v; }
        };

        $this->registry->registerHooksOn($anon);

        // Verify it works before unregistration
        do_action('rt_unreg_action', 'before');
        $this->assertCount(1, $captured);

        // Remove it
        $this->registry->unregisterHooksOn($anon);

        // Should no longer fire
        do_action('rt_unreg_action', 'after');
        $this->assertCount(1, $captured, 'Hook should not fire after unregistration');
    }

    // ── Idempotency / guards ───────────────────────────────────────────

    public function testDoubleRegistrationIsNoOp(): void
    {
        $actions = [];

        $anon = new class ($actions) {
            public function __construct(private array &$actions)
            {}

            #[Action(hook: 'rt_double_reg', acceptedArgs: 1)]
            public function onAction(string $v): void
            {
                $this->actions[] = $v; }
        };

        $this->registry->registerHooksOn($anon);
        $this->registry->registerHooksOn($anon); // second call — no-op

        do_action('rt_double_reg', 'once');

        $this->assertCount(1, $actions, 'Hook should fire exactly once, not twice');
    }

    public function testUnregisterNonRegisteredInstanceIsNoOp(): void
    {
        $anon = new class {};

        // Should not throw
        $this->registry->unregisterHooksOn($anon);

        $this->assertTrue(true);
    }

    // ── Static method skip ─────────────────────────────────────────────

    public function testStaticMethodSkipped(): void
    {
        // Use a named class — anonymous class static methods behave oddly
        $testClass = new class {
            /**
             * @phpstan-ignore-next-line
             * @psalm-suppress InaccessibleMethod
             */
            #[Action(hook: 'rt_static_skip', acceptedArgs: 0)]
            public static function ignoredStatic(): void
            {
                // static method — should be skipped
            }
        };

        $this->registry->registerHooksOn($testClass);

        $this->assertNull(
            $this->findRegisteredHook('action', 'rt_static_skip'),
        );
    }

    // ── Priority ───────────────────────────────────────────────────────

    public function testPriorityOnAction(): void
    {
        $anon = new class {
            #[Action(hook: 'rt_priority_action', priority: 99, acceptedArgs: 0)]
            public function customPriority(): void
            {
            }
        };

        $this->registry->registerHooksOn($anon);

        $hook = $this->findRegisteredHook('action', 'rt_priority_action');
        $this->assertNotNull($hook);
        $this->assertSame(99, $hook['priority']);
    }

    public function testPriorityOnFilter(): void
    {
        $anon = new class {
            #[Filter(hook: 'rt_priority_filter', priority: 5, acceptedArgs: 1)]
            public function lowPriority(string $v): string
            {
                return $v;
            }
        };

        $this->registry->registerHooksOn($anon);

        $hook = $this->findRegisteredHook('filter', 'rt_priority_filter');
        $this->assertNotNull($hook);
        $this->assertSame(5, $hook['priority']);
    }

    // ── Error handling ─────────────────────────────────────────────────

    public function testFilterPassthroughOnError(): void
    {
        $anon = new class {
            #[Filter(hook: 'rt_filter_error', acceptedArgs: 1)]
            public function willThrow(string $_v): string
            {
                throw new \RuntimeException('simulated failure');
            }
        };

        $this->registry->registerHooksOn($anon);

        $result = apply_filters('rt_filter_error', 'passthrough');

        $this->assertSame('passthrough', $result);
    }

    public function testActionDoesNotPropagateError(): void
    {
        $anon = new class {
            #[Action(hook: 'rt_action_error', acceptedArgs: 1)]
            public function willThrow(string $_v): void
            {
                throw new \RuntimeException('simulated failure');
            }
        };

        $this->registry->registerHooksOn($anon);

        do_action('rt_action_error', 'payload');

        $this->assertTrue(true, 'Exception should not propagate');
    }

    // ── Accepted args ──────────────────────────────────────────────────

    public function testAcceptedArgsLimitsArguments(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Filter(hook: 'rt_limited_args', acceptedArgs: 2)]
            public function onFilter(string $first, string $second): string
            {
                $this->captured[] = [$first, $second];
                return $first . '_' . $second;
            }
        };

        $this->registry->registerHooksOn($anon);

        $result = apply_filters('rt_limited_args', 'a', 'b', 'c');

        $this->assertSame('a_b', $result);
        $this->assertCount(1, $captured);
        $this->assertSame(['a', 'b'], $captured[0]);
    }

    // ── Separate registries ────────────────────────────────────────────

    public function testSeparateRegistriesAreIsolated(): void
    {
        $registryA = $this->container()->make(WPHooksRuntimeRegistry::class, [
            'provider' => null,
        ]);
        $registryB = $this->container()->make(WPHooksRuntimeRegistry::class, [
            'provider' => null,
        ]);

        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_isolated_action', acceptedArgs: 1)]
            public function onAction(string $v): void
            {
                $this->captured[] = $v; }
        };

        $registryA->registerHooksOn($anon);

        // registryA should have registered it; registryB is unaware
        do_action('rt_isolated_action', 'registry_a');
        $this->assertCount(1, $captured);

        // Unregister from registryA — registryB won't interfere
        $registryA->unregisterHooksOn($anon);
        do_action('rt_isolated_action', 'after_unreg');
        $this->assertCount(1, $captured);
    }

    // ── Manual registration (registerAction / registerFilter) ─────────

    public function testManualRegisterActionInfersOwnerFromArrayCallable(): void
    {
        $captured = [];

        $owner = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            public function boot(string $value): void
            {
                $this->captured[] = $value;
            }
        };

        $this->registry->registerAction(hook: 'rt_manual_action', callback: [$owner, 'boot']);

        $registered = $this->findRegisteredHook('action', 'rt_manual_action');
        $this->assertNotNull($registered);
        $this->assertInstanceOf(RuntimeCallableHookHandler::class, $registered['callable']);

        do_action('rt_manual_action', 'manual');
        $this->assertSame(['manual'], $captured);
    }

    public function testManualRegisterInfersOwnerFromFirstClassCallable(): void
    {
        $captured = [];

        $owner = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            public function boot(string $value): void
            {
                $this->captured[] = $value;
            }
        };

        $this->registry->registerAction(hook: 'rt_manual_first_class', callback: $owner->boot(...));

        do_action('rt_manual_first_class', 'fc');
        $this->assertSame(['fc'], $captured);
    }

    public function testExplicitOwnerWinsOverInference(): void
    {
        $captured = [];

        $feature = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            public function warm(string $value): void
            {
                $this->captured[] = $value;
            }
        };

        $cache = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            public function warm(string $value): void
            {
                $this->captured[] = $value;
            }
        };

        // Explicit owner: $feature — even though the callback belongs to $cache.
        $this->registry->registerAction(hook: 'rt_manual_owner', callback: [$cache, 'warm'], owner: $feature);

        do_action('rt_manual_owner', 'explicit');
        $this->assertSame(['explicit'], $captured);

        // Unregister via the EXPLICIT owner — the hook must disappear.
        $this->registry->unregisterHooksOn($feature);
        $this->assertNull($this->findRegisteredHook('action', 'rt_manual_owner'));
    }

    public function testOwnerlessStaticClosureThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Cannot infer owner for hook registration — pass owner: explicitly.');

        $this->registry->registerAction(
            hook: 'rt_manual_no_owner',
            callback: static fn(): string => 'noop',
        );
    }

    public function testManualAndAttributeRegistrationMergeUnderOneOwner(): void
    {
        $captured = [];

        $owner = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_merge_attr', acceptedArgs: 1)]
            public function fromAttribute(string $v): void
            {
                $this->captured[] = "attr:$v"; }

            public function fromManual(string $v): void
            {
                $this->captured[] = "manual:$v"; }
        };

        // Manual registration FIRST, then attribute scan — order must not matter.
        $this->registry->registerAction(hook: 'rt_merge_manual', callback: [$owner, 'fromManual']);
        $this->registry->registerHooksOn($owner);

        do_action('rt_merge_attr', 'a');
        do_action('rt_merge_manual', 'm');
        $this->assertSame(['attr:a', 'manual:m'], $captured);

        // Unregistration removes BOTH owned hooks.
        $this->registry->unregisterHooksOn($owner);
        $this->assertNull($this->findRegisteredHook('action', 'rt_merge_attr'));
        $this->assertNull($this->findRegisteredHook('action', 'rt_merge_manual'));
    }

    public function testManualRegistrationDeduplicatesIdenticalHookHandlerPriority(): void
    {
        $captured = [];

        $owner = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            public function boot(string $v): void
            {
                $this->captured[] = $v; }
        };

        $callback = [$owner, 'boot'];
        $this->registry->registerAction(hook: 'rt_manual_dedupe', callback: $callback, priority: 10);
        $this->registry->registerAction(hook: 'rt_manual_dedupe', callback: $callback, priority: 10);

        do_action('rt_manual_dedupe', 'once');
        $this->assertSame(['once'], $captured, 'Identical registration must fire exactly once');
    }

    public function testManualConditionGateControlsFiring(): void
    {
        $captured = [];

        $owner = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            public function boot(string $v): void
            {
                $this->captured[] = $v; }
        };

        // Condition true → fires.
        $this->registry->registerAction(
            hook: 'rt_manual_cond_true',
            callback: [$owner, 'boot'],
            executeIf: fn(): bool => true,
        );
        do_action('rt_manual_cond_true', 'yes');
        $this->assertSame(['yes'], $captured);

        // Condition false → skipped entirely.
        $this->registry->registerAction(
            hook: 'rt_manual_cond_false',
            callback: [$owner, 'boot'],
            executeIf: fn(): bool => false,
        );
        do_action('rt_manual_cond_false', 'no');
        $this->assertSame(['yes'], $captured, 'Condition false must skip the hook');
    }

    public function testManualNonBoolConditionPassesFilterThrough(): void
    {
        $owner = new class {
            public function transform(string $v): string
            {
                return $v . '_transformed';
            }
        };

        $this->registry->registerFilter(
            hook: 'rt_manual_cond_bad',
            callback: [$owner, 'transform'],
            executeIf: fn() => 'not-a-bool',
        );

        // Non-bool condition → RuntimeException caught → logged + passthrough.
        $result = apply_filters('rt_manual_cond_bad', 'input');
        $this->assertSame('input', $result);
    }

    public function testManualFilterPassthroughOnHandlerThrow(): void
    {
        $owner = new class {
            public function transform(string $_v): string
            {
                throw new \RuntimeException('simulated manual failure');
            }
        };

        $this->registry->registerFilter(hook: 'rt_manual_throw', callback: [$owner, 'transform']);

        $result = apply_filters('rt_manual_throw', 'passthrough');
        $this->assertSame('passthrough', $result);
    }

    public function testInvalidCallableThrowsAndLogs(): void
    {
        $owner = new class {
            public function boot(): void
            {
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('callback is not callable');

        $this->registry->registerAction(hook: 'rt_manual_invalid', callback: [$owner, 'missingMethod']);
    }

    // ── Closure support on the attribute path (hook / registerIf / executeIf) ────

    public function testClosureHookNameResolvedAndFires(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: static function (): string {
                    return 'rt_closure_hook'; }, acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }
        };

        $this->registry->registerHooksOn($anon);

        $registered = $this->findRegisteredHook('action', 'rt_closure_hook');
        $this->assertNotNull($registered, 'Closure-resolved hook must be registered under the resolved name');
        $this->assertInstanceOf(RuntimeInstanceHookHandler::class, $registered['callable']);

        do_action('rt_closure_hook', 'fired');
        $this->assertSame(['fired'], $captured);
    }

    public function testClosureHookCanAccessPrivateClassStateViaScope(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            private const HOOK_NAME = 'rt_scope_closure_hook';

            public function __construct(private array &$captured)
            {}

            #[Action(hook: static function (): string {
                    return self::HOOK_NAME; }, acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }
        };

        $this->registry->registerHooksOn($anon);

        $this->assertNotNull($this->findRegisteredHook('action', 'rt_scope_closure_hook'));

        do_action('rt_scope_closure_hook', 'fired');
        $this->assertSame(['fired'], $captured);
    }

    public function testClosureRegisterIfSkipsRegistrationWhenFalse(): void
    {
        $anon = new class {
            #[Action(hook: 'rt_closure_register_false', registerIf: static function (): bool {
                    return false; })]
            public function doSomething(): void
            {
            }
        };

        $this->registry->registerHooksOn($anon);

        $this->assertNull($this->findRegisteredHook('action', 'rt_closure_register_false'));
    }

    public function testClosureRegisterIfRegistersWhenTrue(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_closure_register_true', registerIf: static function (): bool {
                    return true; }, acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }
        };

        $this->registry->registerHooksOn($anon);

        $this->assertNotNull($this->findRegisteredHook('action', 'rt_closure_register_true'));

        do_action('rt_closure_register_true', 'fired');
        $this->assertSame(['fired'], $captured);
    }

    public function testClosureExecuteIfGatesAttributeAction(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_closure_execute_gated', executeIf: static function (): bool {
                    return false; }, acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }

            #[Action(hook: 'rt_closure_execute_open', executeIf: static function (): bool {
                    return true; }, acceptedArgs: 1)]
            public function doOther(string $val): void
            {
                $this->captured[] = $val; }
        };

        $this->registry->registerHooksOn($anon);

        // Gate false → registered but never fires.
        $this->assertNotNull($this->findRegisteredHook('action', 'rt_closure_execute_gated'));
        do_action('rt_closure_execute_gated', 'blocked');
        $this->assertSame([], $captured);

        // Gate true → fires.
        do_action('rt_closure_execute_open', 'open');
        $this->assertSame(['open'], $captured);
    }

    public function testClosureExecuteIfFilterPassesThroughWhenFalse(): void
    {
        $anon = new class {
            #[Filter(hook: 'rt_closure_execute_filter', executeIf: static function (): bool {
                    return false; }, acceptedArgs: 2)]
            public function transform(string $val, string $extra = ''): string
            {
                return $val . $extra;
            }
        };

        $this->registry->registerHooksOn($anon);

        $this->assertNotNull($this->findRegisteredHook('filter', 'rt_closure_execute_filter'));

        $result = apply_filters('rt_closure_execute_filter', 'original');
        $this->assertSame('original', $result);
    }

    // ── RuntimeWPHookProvider dependency resolution ────

    public function testProviderResolvesExecuteIfContainerDependencies(): void
    {
        $container = (new ContainerBuilder())
            ->useAutowiring(true)
            ->addDefinitions([RuntimeProviderFlagService::class => \DI\autowire(RuntimeProviderFlagService::class)])
            ->build();
        $registry = new WPHooksRuntimeRegistry(new HookRuntimeResolver(), new WPHooksRuntimeCache(),new RuntimeWPHookProvider($container));
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_provider_execute', executeIf: static function (RuntimeProviderFlagService $flag): bool {
                    return $flag->isEnabled(); }, acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }
        };

        $registry->registerHooksOn($anon);

        $this->assertNotNull($this->findRegisteredHook('action', 'rt_provider_execute'));
        do_action('rt_provider_execute', 'fired');
        $this->assertSame(['fired'], $captured);
    }

    public function testProviderExecuteIfGateFalseSkipsFire(): void
    {
        $container = (new ContainerBuilder())
            ->useAutowiring(true)
            ->addDefinitions([RuntimeProviderFlagService::class => \DI\autowire(RuntimeProviderFlagService::class)])
            ->build();
        $container->get(RuntimeProviderFlagService::class)->enabled = false;

        $registry = new WPHooksRuntimeRegistry(new HookRuntimeResolver(), new WPHooksRuntimeCache(), new RuntimeWPHookProvider($container));
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_provider_execute_false', executeIf: static function (RuntimeProviderFlagService $flag): bool {
                    return $flag->isEnabled(); }, acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }
        };

        $registry->registerHooksOn($anon);

        $this->assertNotNull($this->findRegisteredHook('action', 'rt_provider_execute_false'));
        do_action('rt_provider_execute_false', 'blocked');
        $this->assertSame([], $captured);
    }

    public function testProviderRegisterIfUsesDefaultParameters(): void
    {
        $registry = new WPHooksRuntimeRegistry(new HookRuntimeResolver(), new WPHooksRuntimeCache(), new RuntimeWPHookProvider());

        $anon = new class {
            #[Action(hook: 'rt_provider_register_default_false', registerIf: static function (bool $flag = false): bool {
                    return $flag; })]
            public function doFalse(): void
            {
            }

            #[Action(hook: 'rt_provider_register_default_true', registerIf: static function (bool $flag = true): bool {
                    return $flag; })]
            public function doTrue(): void
            {
            }
        };

        $registry->registerHooksOn($anon);

        // Default false → gate false → skipped.
        $this->assertNull($this->findRegisteredHook('action', 'rt_provider_register_default_false'));
        // Default true → gate true → registered.
        $this->assertNotNull($this->findRegisteredHook('action', 'rt_provider_register_default_true'));
    }

    // ── Runtime once (consume-on-any-evaluation) ──

    public function testOnceActionFiresOnceThenAutoRemoves(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_once_action', once: true, acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }
        };

        $this->registry->registerHooksOn($anon);

        $this->assertNotNull($this->findRegisteredHook('action', 'rt_once_action'));

        do_action('rt_once_action', 'fired');
        $this->assertSame(['fired'], $captured);

        // The registration consumed itself on the first fire.
        $this->assertNull($this->findRegisteredHook('action', 'rt_once_action'));

        do_action('rt_once_action', 'again');
        $this->assertSame(['fired'], $captured);
    }

    public function testOnceActionWithFailingExecuteIfConsumesWithoutFiring(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_once_execute_false', once: true, executeIf: static function (): bool {
                    return false; }, acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }
        };

        $this->registry->registerHooksOn($anon);

        $this->assertNotNull($this->findRegisteredHook('action', 'rt_once_execute_false'));

        do_action('rt_once_execute_false', 'blocked');
        $this->assertSame([], $captured);

        // Gate false still consumes the once-fire — no retry.
        $this->assertNull($this->findRegisteredHook('action', 'rt_once_execute_false'));
    }

    public function testOnceFilterInterceptsOnceThenPassesThrough(): void
    {
        $anon = new class {
            #[Filter(hook: 'rt_once_filter', once: true, acceptedArgs: 1)]
            public function transform(string $val): string
            {
                return strtoupper($val);
            }
        };

        $this->registry->registerHooksOn($anon);

        $this->assertNotNull($this->findRegisteredHook('filter', 'rt_once_filter'));

        $first = apply_filters('rt_once_filter', 'alpha');
        $this->assertSame('ALPHA', $first);

        $this->assertNull($this->findRegisteredHook('filter', 'rt_once_filter'));

        $second = apply_filters('rt_once_filter', 'beta');
        $this->assertSame('beta', $second);
    }

    // ── Runtime registerUnderHook (deferRegisterUntilHook) ────

    public function testRegisterUnderHookDefersUntilTriggerFires(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_deferred_hook', deferRegisterUntilHook: 'rt_trigger', acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }
        };

        $this->registry->registerHooksOn($anon);

        // Not registered yet — waiting for the trigger hook.
        $this->assertNull($this->findRegisteredHook('action', 'rt_deferred_hook'));

        do_action('rt_trigger');

        // Trigger fired → auto-activated.
        $this->assertNotNull($this->findRegisteredHook('action', 'rt_deferred_hook'));

        do_action('rt_deferred_hook', 'fired');
        $this->assertSame(['fired'], $captured);
    }

    public function testRegisterUnderHookRespectsRegisterIfAtActivation(): void
    {
        $anon = new class {
            #[Action(hook: 'rt_deferred_gated_hook', deferRegisterUntilHook: 'rt_trigger', registerIf: static function (): bool {
                    return false; })]
            public function doSomething(): void
            {
            }
        };

        $this->registry->registerHooksOn($anon);

        do_action('rt_trigger');

        // Gate rejected at activation → never registers.
        $this->assertNull($this->findRegisteredHook('action', 'rt_deferred_gated_hook'));
    }

    public function testUnregisterHooksOnSweepsDeferredPool(): void
    {
        $anon = new class {
            #[Action(hook: 'rt_deferred_swept_hook', deferRegisterUntilHook: 'rt_trigger')]
            public function doSomething(): void
            {
            }
        };

        $this->registry->registerHooksOn($anon);

        $this->registry->unregisterHooksOn($anon);

        do_action('rt_trigger');

        // Deferred entry was swept — the trigger must not activate it.
        $this->assertNull($this->findRegisteredHook('action', 'rt_deferred_swept_hook'));
    }

    // ── Runtime instance-lifetime (GC-aware auto-cleanup) ────

    public function testInstanceDeathNukesActiveHooks(): void
    {
        $captured = [];

        $anon = new class ($captured) {
            public function __construct(private array &$captured)
            {}

            #[Action(hook: 'rt_lifetime_action', acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
                $this->captured[] = $val; }
        };

        $this->registry->registerHooksOn($anon);

        $this->assertNotNull($this->findRegisteredHook('action', 'rt_lifetime_action'));

        // Drop the only strong reference — the handler holds a WeakReference,
        // so the owner is garbage-collected and the hook nukes itself.
        unset($anon);
        gc_collect_cycles();

        do_action('rt_lifetime_action', 'x');
        $this->assertSame([], $captured);
        $this->assertNull($this->findRegisteredHook('action', 'rt_lifetime_action'));
    }

    public function testInstanceDeathPreventsDeferredActivation(): void
    {
        $anon = new class {
            #[Action(hook: 'rt_lifetime_deferred', deferRegisterUntilHook: 'rt_trigger', acceptedArgs: 1)]
            public function doSomething(string $val): void
            {
            }
        };

        $this->registry->registerHooksOn($anon);

        unset($anon);
        gc_collect_cycles();

        // Owner is dead — the deferred entry must never activate.
        do_action('rt_trigger');
        $this->assertNull($this->findRegisteredHook('action', 'rt_lifetime_deferred'));
    }

    // ── Property hook args (callable parameter names) ────

    public function testPropertyHookExecuteIfResolvesHookArgsByName(): void
    {
        $container = (new ContainerBuilder())->useAutowiring(true)->build();
        $registry = new WPHooksRuntimeRegistry(new HookRuntimeResolver(), new WPHooksRuntimeCache() ,new RuntimeWPHookProvider($container));

        $anon = new class {
            #[Filter(hook: 'rt_prop_execute', executeIf: static function (string $value): bool {
                    return $value === 'go'; }, acceptedArgs: 1)]
            public $transform = static function (string $value): string {
                    return strtoupper($value); };
        };

        $registry->registerHooksOn($anon);

        // executeIf's 'value' parameter is name-matched from the property
        // callable's hook args — gate passes for 'go'.
        $this->assertSame('GO', apply_filters('rt_prop_execute', 'go'));

        // Gate false → filter passes the original value through.
        $this->assertSame('no', apply_filters('rt_prop_execute', 'no'));
    }

    public function testPropertyHookInvokableObjectDefaultResolvesHookArgs(): void
    {
        $container = (new ContainerBuilder())->useAutowiring(true)->build();
        $registry = new WPHooksRuntimeRegistry(new HookRuntimeResolver(), new WPHooksRuntimeCache() ,new RuntimeWPHookProvider($container));

        $anon = new class {
            #[Filter(hook: 'rt_prop_invokable', executeIf: static function (string $value): bool { return $value === 'go'; }, acceptedArgs: 1)]
            public $transform = [RuntimePropInvokable::class, 'transform'];
        };

        $registry->registerHooksOn($anon);

        // executeIf's 'value' parameter is name-matched from the __invoke
        // parameter names (invokable-object callable shape) — gate passes.
        $this->assertSame('GO', apply_filters('rt_prop_invokable', 'go'));

        // Gate false → filter passes the original value through.
        $this->assertSame('no', apply_filters('rt_prop_invokable', 'no'));
    }

    // ── Property hook args (getter pattern / live value) ────

    public function testPropertyHookGetterPatternResolvesHookArgs(): void
    {
        $container = (new ContainerBuilder())->useAutowiring(true)->build();
        $registry = new WPHooksRuntimeRegistry(new HookRuntimeResolver(), new WPHooksRuntimeCache() ,new RuntimeWPHookProvider($container));

        $anon = new class {
            #[Filter(hook: 'rt_prop_getter', executeIf: static function (string $value): bool { return $value === 'go'; }, acceptedArgs: 1)]
            public $transform = null {
                get => $this->transform ??= new RuntimePropInvokable();
            }
        };

        $registry->registerHooksOn($anon);

        // The live-value read at registration triggers the getter — the
        // invokable instance's __invoke parameter names feed the gate.
        $this->assertSame('GO', apply_filters('rt_prop_getter', 'go'));
        $this->assertSame('no', apply_filters('rt_prop_getter', 'no'));
    }

    // ── Manual API: once + registerUnderHook ────

    public function testManualRegisterActionWithOnceFiresOnceThenAutoRemoves(): void
    {
        $captured = [];
        $owner = new class {};

        $this->registry->registerAction(
            'rt_manual_once',
            static function (string $val) use (&$captured): void { $captured[] = $val; },
            once: true,
            owner: $owner,
        );

        $this->assertNotNull($this->findRegisteredHook('action', 'rt_manual_once'));

        do_action('rt_manual_once', 'fired');
        $this->assertSame(['fired'], $captured);

        // Consumed → auto-removed.
        $this->assertNull($this->findRegisteredHook('action', 'rt_manual_once'));

        do_action('rt_manual_once', 'again');
        $this->assertSame(['fired'], $captured);
    }

    public function testManualRegisterActionWithDeferRegisterUntilHook(): void
    {
        $captured = [];
        $owner = new class {};

        $this->registry->registerAction(
            'rt_manual_deferred',
            static function (string $val) use (&$captured): void { $captured[] = $val; },
            deferRegisterUntilHook: 'rt_trigger',
            owner: $owner,
        );

        // Not registered yet — waiting for the trigger hook.
        $this->assertNull($this->findRegisteredHook('action', 'rt_manual_deferred'));

        do_action('rt_trigger');

        $this->assertNotNull($this->findRegisteredHook('action', 'rt_manual_deferred'));

        do_action('rt_manual_deferred', 'fired');
        $this->assertSame(['fired'], $captured);
    }

    public function testManualRegisterFilterWithOnceInterceptsOnce(): void
    {
        $owner = new class {};

        $this->registry->registerFilter(
            'rt_manual_once_filter',
            static function (string $v): string { return strtoupper($v); },
            once: true,
            owner: $owner,
        );

        $this->assertSame('ALPHA', apply_filters('rt_manual_once_filter', 'alpha'));

        $this->assertNull($this->findRegisteredHook('filter', 'rt_manual_once_filter'));

        $this->assertSame('beta', apply_filters('rt_manual_once_filter', 'beta'));
    }

    // ── Anon self-registering getter pattern (PluginManagement) ───────

    public function testAnonSelfRegisteringGetterPatternInvokes(): void
    {
        $host = new class ($this->registry) {
            public function __construct(private WPHooksRuntimeRegistry $registry) {}

            public $optionActivePlugin {
                get => $this->optionActivePlugin ??= new class ($this->registry) extends AnonClassHookMetadata {
                    private const CONDITION = false;
                    public function __construct(private WPHooksRuntimeRegistry $registry)
                    {
                        parent::__construct('HostClass', 'optionActivePlugin');
                    }

                    public function __invoke(): void
                    {
                        $this->registry->registerHooksOn($this);
                    }

                    #[Filter(hook: 'option_active_plugins', priority: 0, once: true, executeIf: static function (): bool { return self::CONDITION; })]
                    public function devFilter(array $plugins): array
                    {
                        return $plugins;
                    }
                };
            }
        };

        // Trigger the getter → __invoke → registerHooksOn($this).
        $anon = $host->optionActivePlugin;

        // Mimic the container property handler: invoke the anon as a callable,
        // which self-registers on the runtime registry from inside __invoke.
        $anon();

        $this->assertNotNull($this->findRegisteredHook('filter', 'option_active_plugins'));

        $result = apply_filters('option_active_plugins', ['a', 'b']);
        $this->assertSame(['a', 'b'], $result);
    }
}

/**
 * Simple container service used to prove RuntimeWPHookProvider dependency
 * resolution on the runtime attribute path.
 */
class RuntimeProviderFlagService
{
    public bool $enabled = true;

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}

/**
 * Invokable-adjacent callable used as a property-hook default to prove
 * callable-shape hookArgs extraction (parameter names come from the
 * static transform method).
 */
class RuntimePropInvokable
{
    public function __invoke(string $value): string
    {
        return strtoupper($value);
    }

    public static function transform(string $value): string
    {
        return strtoupper($value);
    }
}
