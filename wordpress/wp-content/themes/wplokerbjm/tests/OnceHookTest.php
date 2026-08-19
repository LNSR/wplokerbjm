<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\Container;
use DI\ContainerBuilder;
use WPLokerBJM\Tests\Support\Fixtures\OnceActionService;
use WPLokerBJM\Tests\Support\Fixtures\OnceFilterService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Test suite for the `once` flag on hook registrations.
 *
 * Verifies the consume-on-any-evaluation contract: the registration
 * removes itself after its FIRST fire where the executeIf gate is
 * evaluated — regardless of whether the gate passed, failed, or could
 * not be evaluated at all. A hook without a gate is consumed on its
 * first fire.
 */
class OnceHookTest extends WplokerbjmTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh, isolated container per test — only the once fixtures.
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            OnceActionService::class => \DI\autowire(),
            OnceFilterService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();

        OnceActionService::reset();
        OnceFilterService::reset();
    }

    public function testOnceActionWithoutGateFiresOnceThenRemovesItself(): void
    {
        $registrations = [
            $this->action(OnceActionService::class, 'onOnceAction', 'once_no_gate', once: true),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', 'once_no_gate'));

        do_action('once_no_gate', 'first');

        $this->assertSame(1, OnceActionService::$instantiationCount);
        $this->assertSame(['first'], OnceActionService::$capturedValues);

        // Consume-on-any-evaluation: registration gone from the active pool.
        $this->assertNull($this->findRegisteredHook('action', 'once_no_gate'));

        // Second fire is a no-op — nothing registered anymore.
        do_action('once_no_gate', 'second');

        $this->assertSame(1, OnceActionService::$instantiationCount);
        $this->assertSame(['first'], OnceActionService::$capturedValues);
    }

    public function testConsecutiveOnceActionsOnSameHookBothFireDuringDispatch(): void
    {
        // Regression: during a real WordPress dispatch, doing_action() returns
        // true for the hook currently firing. The once-consume must then SKIP
        // the WordPress-side removal — otherwise WP_Hook::resort_active_iterations
        // repositions the iteration pointer and the immediately-following
        // priority never fires (consecutive once-hooks on the same hook).
        \Brain\Monkey\Functions\when('doing_action')->alias(
            static fn (string $hook): bool => $hook === 'once_consecutive'
        );

        $registrations = [
            $this->action(OnceActionService::class, 'onOnceAction', 'once_consecutive', priority: 1, once: true),
            $this->action(OnceActionService::class, 'onOnceAction', 'once_consecutive', priority: 2, once: true),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $this->assertCount(2, array_filter(
            $this->registeredHooks(),
            static fn (array $r): bool => $r['hook'] === 'once_consecutive'
        ));

        do_action('once_consecutive', 'first');

        // BOTH fire — the first once-hook's consume must not skip the second.
        // (PHP-DI resolves OnceActionService as a singleton, so the
        // instantiation count stays 1; the two captured values prove both
        // handlers were invoked.)
        $this->assertSame(1, OnceActionService::$instantiationCount);
        $this->assertSame(['first', 'first'], OnceActionService::$capturedValues);

        // WordPress-side removal was skipped during dispatch (the guard) —
        // the callbacks linger, but are inert (consumed → passthrough).
        $this->assertCount(2, array_filter(
            $this->registeredHooks(),
            static fn (array $r): bool => $r['hook'] === 'once_consecutive'
        ));

        // Second dispatch: the lingering callbacks must not re-fire the service.
        do_action('once_consecutive', 'second');

        $this->assertSame(1, OnceActionService::$instantiationCount);
        $this->assertSame(['first', 'first'], OnceActionService::$capturedValues);
    }

    public function testOnceActionWithPassingGateRunsOnceThenRemoved(): void
    {
        $registrations = [
            $this->action(
                OnceActionService::class,
                'onOnceAction',
                'once_gate_pass',
                once: true,
                executeIf: static fn (): bool => true
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        do_action('once_gate_pass', 'hello');
        do_action('once_gate_pass', 'again');

        $this->assertSame(1, OnceActionService::$instantiationCount);
        $this->assertSame(['hello'], OnceActionService::$capturedValues);
        $this->assertNull($this->findRegisteredHook('action', 'once_gate_pass'));
    }

    public function testOnceActionWithFailingGateConsumesWithoutResolvingService(): void
    {
        $registrations = [
            $this->action(
                OnceActionService::class,
                'onOnceAction',
                'once_gate_fail',
                once: true,
                executeIf: static fn (): bool => false
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', 'once_gate_fail'));

        do_action('once_gate_fail', 'hello');

        // Gate failed → service never resolved, but the registration is
        // still consumed (no retry on the next fire).
        $this->assertSame(0, OnceActionService::$instantiationCount);
        $this->assertSame([], OnceActionService::$capturedValues);
        $this->assertNull($this->findRegisteredHook('action', 'once_gate_fail'));

        do_action('once_gate_fail', 'again');
        $this->assertSame(0, OnceActionService::$instantiationCount);
    }

    public function testOnceFilterInterceptsOnceThenPassesThrough(): void
    {
        $registrations = [
            $this->filter(OnceFilterService::class, 'onOnceFilter', 'once_filter', once: true),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $first = apply_filters('once_filter', 'alpha');

        $this->assertSame('alpha', $first);
        $this->assertSame(1, OnceFilterService::$instantiationCount);
        $this->assertNull($this->findRegisteredHook('filter', 'once_filter'));

        // Registration removed → value passes through untouched.
        $second = apply_filters('once_filter', 'beta');

        $this->assertSame('beta', $second);
        $this->assertSame(1, OnceFilterService::$instantiationCount);
    }

    public function testOnceActionWithUnresolvableGateIsTreatedAsPassAndConsumed(): void
    {
        // Plan entry references a class the container cannot resolve and
        // carries no default → evaluateExecuteIf throws. For once-hooks the
        // gate is treated as PASS (mirrors the deferRegisterUntilHook
        // convention); for regular hooks it is skipped with an error.
        $registrations = [
            $this->action(
                OnceActionService::class,
                'onOnceAction',
                'once_unresolvable',
                once: true,
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

        do_action('once_unresolvable', 'hello');

        $this->assertSame(1, OnceActionService::$instantiationCount);
        $this->assertSame(['hello'], OnceActionService::$capturedValues);
        $this->assertNull($this->findRegisteredHook('action', 'once_unresolvable'));
    }

    public function testOnceActionWithDeferredActivationConsumesAfterFirstFire(): void
    {
        $registrations = [
            $this->action(
                OnceActionService::class,
                'onOnceAction',
                'once_deferred',
                once: true,
                deferRegister: true
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        // Deferred: not registered during initialize()...
        $this->assertNull($this->findRegisteredHook('action', 'once_deferred'));

        // ...activated on demand, then consumed on its first fire.
        $registry->activateDeferredByHook('once_deferred');
        $this->assertNotNull($this->findRegisteredHook('action', 'once_deferred'));

        do_action('once_deferred', 'd1');
        do_action('once_deferred', 'd2');

        $this->assertSame(1, OnceActionService::$instantiationCount);
        $this->assertSame(['d1'], OnceActionService::$capturedValues);
        $this->assertNull($this->findRegisteredHook('action', 'once_deferred'));
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
        bool $once = false,
        ?Closure $executeIf = null,
        bool $deferRegister = false,
        array $executeIfParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'once' => $once,
            'defer_register' => $deferRegister,
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
        bool $once = false,
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
            'once' => $once,
            'defer_register' => $deferRegister,
            'execute_if' => $executeIf,
            'execute_if_params' => $executeIfParams,
        ];
    }
}
