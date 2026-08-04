<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\Container;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredHookManager;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHookPlanProvider;
use WPLokerBJM\Tests\Support\Fixtures\ExecuteIfActionService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Test suite for the `registerIf` registration gate.
 *
 * registerIf is evaluated ONCE at registration time — a false result means
 * the hook is never registered, whether deferred or not. executeIf remains
 * the per-fire execution gate.
 */
class RegisterIfHookTest extends WplokerbjmTestCase
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
            ExecuteIfActionService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();
        $this->planProvider = new WPHookPlanProvider();

        ExecuteIfActionService::reset();
    }

    public function testRegisterIfTrueRegistersAndFires(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'register_if_true',
                registerIf: static fn (): bool => true
            ),
        ], $this->planProvider, new DeferredHookManager($this->planProvider, $this->container));
        $registry->initialize();

        self::assertNotNull($this->findRegisteredHook('action', 'register_if_true'));

        do_action('register_if_true', 'hello');
        self::assertSame(['hello'], ExecuteIfActionService::$capturedValues);
    }

    public function testRegisterIfFalseSkipsRegistrationEntirely(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'register_if_false',
                registerIf: static fn (): bool => false
            ),
        ], $this->planProvider, new DeferredHookManager($this->planProvider, $this->container));
        $registry->initialize();

        // Never registered — no add_action call, nothing fires.
        self::assertNull($this->findRegisteredHook('action', 'register_if_false'));
        self::assertSame([], $this->registeredHooks());

        do_action('register_if_false', 'nope');
        self::assertSame([], ExecuteIfActionService::$capturedValues);
    }

    public function testRegisterIfFalseSkipsDeferredHookEvenAfterActivation(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'register_if_deferred',
                defer: true,
                registerIf: static fn (): bool => false
            ),
        ], $this->planProvider, new DeferredHookManager($this->planProvider, $this->container));
        $registry->initialize();

        // The gate runs BEFORE defer-pool placement — the hook never exists.
        $registry->activateDeferredByHook('register_if_deferred');
        self::assertNull($this->findRegisteredHook('action', 'register_if_deferred'));

        do_action('register_if_deferred', 'nope');
        self::assertSame([], ExecuteIfActionService::$capturedValues);
    }

    public function testRegisterIfNonBoolResultIsLoggedAndSkipped(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'register_if_nonbool',
                registerIf: static fn (): string => 'not-a-bool'
            ),
        ], $this->planProvider, new DeferredHookManager($this->planProvider, $this->container));
        $registry->initialize();

        self::assertNull($this->findRegisteredHook('action', 'register_if_nonbool'));

        do_action('register_if_nonbool', 'nope');
        self::assertSame([], ExecuteIfActionService::$capturedValues);
    }

    public function testRegisterIfThrowingGateIsLoggedAndSkipped(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'register_if_throws',
                registerIf: static function (): bool {
                    throw new \RuntimeException('Simulated registration gate failure');
                }
            ),
        ], $this->planProvider, new DeferredHookManager($this->planProvider, $this->container));
        $registry->initialize();

        self::assertNull($this->findRegisteredHook('action', 'register_if_throws'));
    }

    public function testRegisterIfReceivesContainerResolvedParams(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'register_if_di',
                registerIf: static function (ExecuteIfActionService $service): bool {
                    return $service !== null;
                }
            ),
        ], $this->planProvider, new DeferredHookManager($this->planProvider, $this->container));
        $registry->initialize();

        // registerIf params resolve from the container (reflection fallback path).
        self::assertNotNull($this->findRegisteredHook('action', 'register_if_di'));

        do_action('register_if_di', 'di');
        self::assertSame(['di'], ExecuteIfActionService::$capturedValues);
    }

    public function testRegisterIfPlanIsBuiltByProvider(): void
    {
        // ContainerInterface-typed param → typed plan entry.
        $plan = $this->planProvider->buildCallablePlan(
            static function (ContainerInterface $c): bool {
                return $c->has(ExecuteIfActionService::class);
            }
        );
        self::assertCount(1, $plan);
        self::assertSame('c', $plan[0]['name']);
        self::assertSame(ContainerInterface::class, $plan[0]['type']);
        self::assertFalse($plan[0]['hasDefault']);

        // Zero-parameter gate → empty plan.
        self::assertSame([], $this->planProvider->buildCallablePlan(static fn (): bool => true));

        // Null gate → empty plan.
        self::assertSame([], $this->planProvider->buildCallablePlan(null));
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
        bool $defer = false,
        ?Closure $registerIf = null,
        array $executeIfParams = [],
        array $registerIfParams = [],
        array $tags = []
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
            'register_if' => $registerIf,
            'register_if_params' => $registerIfParams,
            'tags' => $tags,
        ];
    }
}
