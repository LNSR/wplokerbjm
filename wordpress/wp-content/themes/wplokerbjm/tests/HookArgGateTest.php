<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use DI\Container;
use DI\ContainerBuilder;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Tests\Support\Fixtures\HookArgSearchService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Hook arguments are injected into executeIf gates by exact parameter name:
 * the handler's declared parameter names (captured by the scanner as
 * hookArgs) are matched against the closure's parameter names — zero
 * scalar ambiguity, params may be reordered, unknown names fall back to
 * the container.
 */
class HookArgGateTest extends WplokerbjmTestCase
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
            HookArgSearchService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();
        $this->planProvider = new WPHookPlanProvider();

        HookArgSearchService::reset();
    }

    private function createRegistryWith(array $registrations): WPHooksContainerRegistry
    {
        return $this->createRegistry($registrations, $this->container);
    }

    public function testHookArgsInjectedByNameFiresOnInspection(): void
    {
        // Handler method signature: onSearch(string $search, string $extra).
        // The closure matches $search by name (reordered params prove the
        // name-match — $extra declared first here).
        $gate = static function (string $extra, string $search): bool {
            return $search === 'lowongan' && $extra === 'extra';
        };

        $registrations = [
            $this->action(
                HookArgSearchService::class,
                'onSearch',
                'hook_arg_gate',
                acceptedArgs: 2,
                executeIf: $gate,
                executeIfParams: $this->planProvider->buildCallablePlan($gate),
            ),
        ];

        $registry = $this->createRegistryWith($registrations);
        $registry->initialize();

        // Args map: onSearch('lowongan', 'extra') → $search='lowongan', $extra='extra'.
        do_action('hook_arg_gate', 'lowongan', 'extra');

        self::assertSame(['lowongan'], HookArgSearchService::$capturedValues);
    }

    public function testHookArgsInjectedByNameSkipsOnInspection(): void
    {
        $gate = static function (string $search): bool {
            return $search === 'lowongan';
        };

        $registrations = [
            $this->action(
                HookArgSearchService::class,
                'onSearch',
                'hook_arg_gate_skip',
                executeIf: $gate,
                executeIfParams: $this->planProvider->buildCallablePlan($gate),
            ),
        ];

        $registry = $this->createRegistryWith($registrations);
        $registry->initialize();

        do_action('hook_arg_gate_skip', 'other', 'extra');

        self::assertSame([], HookArgSearchService::$capturedValues);
    }

    public function testUnknownParamNameFallsBackToContainer(): void
    {
        // $service is not a handler arg name → resolved from the container.
        $gate = static function (HookArgSearchService $service, string $search): bool {
            return $service !== null && $search === 'lowongan';
        };

        $registrations = [
            $this->action(
                HookArgSearchService::class,
                'onSearch',
                'hook_arg_gate_container',
                executeIf: $gate,
                executeIfParams: $this->planProvider->buildCallablePlan($gate),
            ),
        ];

        $registry = $this->createRegistryWith($registrations);
        $registry->initialize();

        do_action('hook_arg_gate_container', 'lowongan', 'extra');

        self::assertSame(['lowongan'], HookArgSearchService::$capturedValues);
    }

    public function testFewerHookArgsThanNamesIsCountGuarded(): void
    {
        // Handler has 2 params but only 1 arg is passed → array_slice guard
        // keeps the name→value map to 1 entry; $extra has no match → falls to
        // container → not resolvable → gate throws → skipped.
        $gate = static function (string $search, string $extra): bool {
            return $search !== '' && $extra !== '';
        };

        $registrations = [
            $this->action(
                HookArgSearchService::class,
                'onSearch',
                'hook_arg_gate_guard',
                executeIf: $gate,
                executeIfParams: $this->planProvider->buildCallablePlan($gate),
            ),
        ];

        $registry = $this->createRegistryWith($registrations);
        $registry->initialize();

        do_action('hook_arg_gate_guard', 'lowongan');

        self::assertSame([], HookArgSearchService::$capturedValues);
    }

    private function action(
        string $class,
        string $method,
        string $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?\Closure $executeIf = null,
        array $executeIfParams = []
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'defer_register' => false,
            'execute_if' => $executeIf,
            'execute_if_params' => $executeIfParams,
            // The registration array must carry the handler param names —
            // normally the scanner emits them (hook_args key).
            'hook_args' => ['search', 'extra'],
            'register_if' => null,
            'register_if_params' => [],
            'hook_params' => [],
            'tags' => [],
        ];
    }
}
