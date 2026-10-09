<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use DI\Container;
use DI\ContainerBuilder;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Tests\Support\Fixtures\VariadicHookService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Variadic hook-argument forwarding (plan-B).
 *
 * `hookArgs` is the handler's own CallableHookParams plan; a trailing
 * isVariadic parameter makes the runtime forward the whole argument
 * remainder truthfully under that name — mirrors
 * server/Core/Container/Test.php and SearchJobHooks.php usage.
 */
final class VariadicHookArgTest extends WplokerbjmTestCase
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
            VariadicHookService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();
        $this->planProvider = new WPHookPlanProvider();
        VariadicHookService::reset();
    }

    public function testPureVariadicHandlerForwardsEveryArgumentToVariadicExecuteIf(): void
    {
        $executeIf = static function (mixed ...$args): bool {
            return \count($args) === 4 && $args[0] === 'alpha' && $args[3] === 'delta';
        };

        $registrations = [[
            'class' => VariadicHookService::class,
            'method' => 'collectAll',
            'type' => 'action',
            'hook' => 'variadic_all',
            'priority' => 10,
            'acceptedArgs' => 4,
            'once' => false,
            'hookArgs' => [
                ['name' => 'args', 'type' => null, 'hasDefault' => false, 'default' => null, 'isVariadic' => true],
            ],
            'executeIf' => $executeIf,
            'executeIfParams' => $this->planProvider->buildCallablePlan($executeIf),
        ]];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        \do_action('variadic_all', 'alpha', 'beta', 'gamma', 'delta');

        $this->assertCount(1, VariadicHookService::$captured);
        $this->assertSame(['alpha', 'beta', 'gamma', 'delta'], VariadicHookService::$captured[0]);
    }

    public function testNamedHeadPlusVariadicTailSplitsSourceFromRemainder(): void
    {
        $executeIf = static function (VariadicHookService $service, mixed ...$args): bool {
            return $service instanceof VariadicHookService && \count($args) === 4 && $args[0] === 'beta';
        };

        $registrations = [[
            'class' => VariadicHookService::class,
            'method' => 'collectTail',
            'type' => 'action',
            'hook' => 'variadic_tail',
            'priority' => 10,
            'acceptedArgs' => 5,
            'once' => false,
            'hookArgs' => [
                ['name' => 'source', 'type' => null, 'hasDefault' => false, 'default' => null, 'isVariadic' => false],
                ['name' => 'args', 'type' => null, 'hasDefault' => false, 'default' => null, 'isVariadic' => true],
            ],
            'executeIf' => $executeIf,
            'executeIfParams' => $this->planProvider->buildCallablePlan($executeIf),
        ]];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        \do_action('variadic_tail', 'source1', 'beta', 'gamma', 'delta', 'eps');

        $this->assertCount(1, VariadicHookService::$captured);
        $this->assertSame('source1', VariadicHookService::$captured[0][0]);
        $this->assertSame(['beta', 'gamma', 'delta', 'eps'], VariadicHookService::$captured[0][1]);
    }

    public function testNamedDescriptorHandlerStillInjectsNamedHookArgIntoExecuteIf(): void
    {
        $executeIf = static function (mixed $value): bool {
            return $value === 'go';
        };

        $registrations = [[
            'class' => VariadicHookService::class,
            'method' => 'collectNamed',
            'type' => 'action',
            'hook' => 'named_descriptor',
            'priority' => 10,
            'acceptedArgs' => 1,
            'once' => false,
            'hookArgs' => [
                ['name' => 'value', 'type' => null, 'hasDefault' => false, 'default' => null, 'isVariadic' => false],
            ],
            'executeIf' => $executeIf,
            'executeIfParams' => $this->planProvider->buildCallablePlan($executeIf),
        ]];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        \do_action('named_descriptor', 'go');
        \do_action('named_descriptor', 'stop');

        $this->assertSame(['go'], VariadicHookService::$captured);
    }

    public function testLegacyStringHookArgsStillResolveNamedHookArg(): void
    {
        $executeIf = static function (mixed $value): bool {
            return $value === 'go';
        };

        $registrations = [[
            'class' => VariadicHookService::class,
            'method' => 'collectNamed',
            'type' => 'action',
            'hook' => 'legacy_names',
            'priority' => 10,
            'acceptedArgs' => 1,
            'once' => false,
            'hookArgs' => ['value'],
            'executeIf' => $executeIf,
            'executeIfParams' => $this->planProvider->buildCallablePlan($executeIf),
        ]];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        \do_action('legacy_names', 'go');
        \do_action('legacy_names', 'stop');

        $this->assertSame(['go'], VariadicHookService::$captured);
    }
}