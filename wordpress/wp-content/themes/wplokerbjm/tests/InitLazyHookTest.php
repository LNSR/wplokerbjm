<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use DI\ContainerBuilder;
use DI\Container;
use WPLokerBJM\Core\Container\Init;
use WPLokerBJM\Core\Container\Support\WPHooks\{WPHookPlanProvider, WPHooksRegistry, LazyHookHandler};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Tests\Support\Fixtures\FilterService;
use WPLokerBJM\Tests\Support\Fixtures\LazyHookService;

/**
 * Test suite for the lazy hook resolution path in {@see Init}.
 *
 * Verifies that:
 *  - Instance hooks are NOT resolved at registration time.
 *  - The first fire triggers container resolution.
 *  - Subsequent fires reuse the same instance via the container's cache.
 *  - Hook arguments are forwarded to the target method.
 *  - Misconfigured hooks (missing class) are logged and skipped.
 *  - `initialize()` is idempotent.
 */
class InitLazyHookTest extends WplokerbjmTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Build a fresh, isolated container per test. Only the fixture
        // services are registered; everything else stays out of the test.
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            LazyHookService::class => \DI\autowire(),
            FilterService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();

        // Reset all fixture state so tests are order-independent.
        LazyHookService::reset();
        FilterService::reset();
    }

    /**
     * Create an Init instance wired to WPHooksRegistry for the given registrations.
     *
     * @param array<int,array<string,mixed>> $registrations
     */
    private function createInit(array $registrations): Init
    {
        $registry = new WPHooksRegistry($this->container, $registrations, new WPHookPlanProvider());
        return new Init($registry);
    }

    public function testInstanceHookIsNotResolvedAtRegistration(): void
    {
        $registrations = [
            $this->action(LazyHookService::class, 'onAction', 'lazy_action_hook'),
        ];

        $init = $this->createInit($registrations);
        $init->initialize();

        // The hook must be registered...
        $reg = $this->findRegisteredHook('action', 'lazy_action_hook');
        $this->assertNotNull($reg, 'Hook should be registered');
        $this->assertInstanceOf(LazyHookHandler::class, $reg['callable'], 'Hook callable should be a LazyHookHandler');

        // ...but the service MUST NOT have been instantiated yet.
        $this->assertSame(
            0,
            LazyHookService::$instantiationCount,
            'Service should not be instantiated at registration time (lazy)'
        );
    }

    public function testInstanceHookResolvesOnFirstFire(): void
    {
        $registrations = [
            $this->action(LazyHookService::class, 'onAction', 'lazy_action_hook'),
        ];

        $init = $this->createInit($registrations);
        $init->initialize();

        $this->assertSame(0, LazyHookService::$instantiationCount, 'Not yet fired');

        do_action('lazy_action_hook', 'first');

        $this->assertSame(
            1,
            LazyHookService::$instantiationCount,
            'Service should be instantiated on first hook fire'
        );
        $this->assertCount(1, LazyHookService::$capturedValues);
        $this->assertSame('first', LazyHookService::$capturedValues[0]['value']);
    }

    public function testInstanceHookReusesResolvedService(): void
    {
        $registrations = [
            $this->action(LazyHookService::class, 'onAction', 'lazy_action_hook'),
        ];

        $init = $this->createInit($registrations);
        $init->initialize();

        do_action('lazy_action_hook', 'a');
        $firstId = LazyHookService::$capturedValues[0]['id'];

        do_action('lazy_action_hook', 'b');
        $secondId = LazyHookService::$capturedValues[1]['id'];

        $this->assertSame(
            1,
            LazyHookService::$instantiationCount,
            'Subsequent fires must reuse the same instance (no extra construction)'
        );
        $this->assertSame(
            $firstId,
            $secondId,
            'All fires must operate on the same service instance'
        );
    }

    public function testLazyFilterForwardsArguments(): void
    {
        $registrations = [
            $this->filter(FilterService::class, 'onFilter', 'lazy_filter_hook', acceptedArgs: 2),
        ];

        $init = $this->createInit($registrations);
        $init->initialize();

        $this->assertSame(0, FilterService::$instantiationCount, 'Not yet fired');

        $result = apply_filters('lazy_filter_hook', 'foo', '!bar');

        $this->assertSame(1, FilterService::$instantiationCount);
        $this->assertSame('foo!bar', $result, 'Filter should forward both arguments');
        $this->assertCount(1, FilterService::$capturedArgs);
        $this->assertSame('foo', FilterService::$capturedArgs[0]['value']);
        $this->assertSame('!bar', FilterService::$capturedArgs[0]['extra']);
    }

    public function testMissingServiceLogsAndSkipsRegistration(): void
    {
        $registrations = [
            $this->action('WPLokerBJM\\Tests\\Support\\Fixtures\\NotInContainer', 'onAction', 'missing_hook'),
            $this->action(LazyHookService::class, 'onAction', 'present_hook'),
        ];

        $init = $this->createInit($registrations);
        $init->initialize();

        $this->assertNull(
            $this->findRegisteredHook('action', 'missing_hook'),
            'Hook for missing class must NOT be registered'
        );
        $this->assertNotNull(
            $this->findRegisteredHook('action', 'present_hook'),
            'Hook for present class must still be registered'
        );
    }

    public function testInitializeIsIdempotent(): void
    {
        $registrations = [
            $this->action(LazyHookService::class, 'onAction', 'lazy_action_hook'),
        ];

        $init = $this->createInit($registrations);
        $init->initialize();
        $init->initialize();
        $init->initialize();

        $matching = array_filter(
            $this->registeredHooks(),
            fn($r) => $r['type'] === 'action' && $r['hook'] === 'lazy_action_hook'
        );
        $this->assertCount(1, $matching, 'Hook should only be registered once even with multiple initialize() calls');
    }

    public function testMultipleServicesOnSameHookFireIndependently(): void
    {
        $registrations = [
            $this->action(LazyHookService::class, 'onAction', 'mixed_hook'),
            $this->filter(FilterService::class, 'onFilter', 'mixed_hook', acceptedArgs: 1),
        ];

        $init = $this->createInit($registrations);
        $init->initialize();

        $this->assertSame(0, LazyHookService::$instantiationCount, 'Lazy service should not be instantiated yet');
        $this->assertSame(0, FilterService::$instantiationCount, 'Lazy filter service should not be instantiated yet');

        // do_action fires only action-typed registrations for the hook,
        // apply_filters fires only filter-typed ones. Both must trigger
        // lazy resolution on the same hook name.
        do_action('mixed_hook', 'payload');
        apply_filters('mixed_hook', 'value');

        $this->assertSame(1, LazyHookService::$instantiationCount, 'Action service should be instantiated exactly once');
        $this->assertSame(1, FilterService::$instantiationCount, 'Filter service should be instantiated exactly once');
        $this->assertSame('payload', LazyHookService::$capturedValues[0]['value']);
        $this->assertSame('value', FilterService::$capturedArgs[0]['value']);
    }

    /**
     * @return array<string,mixed>
     */
    private function action(string $class, string $method, string $hook, int $priority = 10, int $acceptedArgs = 1): array
    {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function filter(string $class, string $method, string $hook, int $priority = 10, int $acceptedArgs = 1): array
    {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'filter',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
        ];
    }
}
