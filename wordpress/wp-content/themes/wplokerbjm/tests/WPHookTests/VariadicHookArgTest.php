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
        /** @suppressWarnings PHP0410 */
        $this->variadicHookService = null;
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            __VariadicHookService::class => $this->variadicHookService,
        ]);
        $this->container = $builder->build();
        $this->planProvider = new WPHookPlanProvider();
    }

    public function testPureVariadicHandlerForwardsEveryArgumentToVariadicExecuteIf(): void
    {
        $executeIf = static function (mixed ...$args): bool {
            return \count($args) === 4 && $args[0] === 'alpha' && $args[3] === 'delta';
        };

        $registrations = [[
            'class' => __VariadicHookService::class,
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

        $this->assertCount(1, $this->variadicHookService->captured);
        $this->assertSame(['alpha', 'beta', 'gamma', 'delta'], $this->variadicHookService->captured[0]);
    }

    public function testNamedHeadPlusVariadicTailSplitsSourceFromRemainder(): void
    {
        $executeIf = static function (__VariadicHookService $service, mixed ...$args): bool {
            return $service instanceof __VariadicHookService && \count($args) === 4 && $args[0] === 'beta';
        };

        $registrations = [[
            'class' => __VariadicHookService::class,
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

        $this->assertCount(1, $this->variadicHookService->captured);
        $this->assertSame('source1', $this->variadicHookService->captured[0][0]);
        $this->assertSame(['beta', 'gamma', 'delta', 'eps'], $this->variadicHookService->captured[0][1]);
    }

    public function testNamedDescriptorHandlerStillInjectsNamedHookArgIntoExecuteIf(): void
    {
        $executeIf = static function (mixed $value): bool {
            return $value === 'go';
        };

        $registrations = [[
            'class' => __VariadicHookService::class,
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

        $this->assertSame(['go'], $this->variadicHookService->captured);
    }

    public function testLegacyStringHookArgsStillResolveNamedHookArg(): void
    {
        $executeIf = static function (mixed $value): bool {
            return $value === 'go';
        };

        $registrations = [[
            'class' => __VariadicHookService::class,
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

        $this->assertSame(['go'], $this->variadicHookService->captured);
    }

    private ?__VariadicHookService $variadicHookService {
        get {
            return $this->variadicHookService ??= new class() {
                public function __construct()
                {
                    !\class_exists(__VariadicHookService::class) && \class_alias(__CLASS__, __VariadicHookService::class);
                }
                
                public array $captured = [];
                /** Pure variadic handler — receives the whole argument list as one bucket. */
                public function collectAll(...$args): void
                {
                    $this->captured[] = $args;
                }

                /** Named head + variadic tail handler. */
                public function collectTail(mixed $source, ...$args): void
                {
                    $this->captured[] = [$source, $args];
                }

                /** Fully named handler (regression shape). */
                public function collectNamed(string $value): void
                {
                    $this->captured[] = $value;
                }
            };
        }
    }
}
