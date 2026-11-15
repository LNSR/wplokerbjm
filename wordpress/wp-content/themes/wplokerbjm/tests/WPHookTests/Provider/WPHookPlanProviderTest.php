<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Provider;

use DI\Container;
use ReflectionMethod;
use ReflectionProperty;
use WPLokerBJM\Core\Container\Attributes\Inject;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\CallableHookParams;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Tests\Support\Fixtures\{PlanProviderDependency, PlanProviderScope};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use function DI\autowire;

/**
 * Dedicated unit coverage for {@see WPHookPlanProvider} — the single DI plan
 * core shared by the container and runtime/instance hook paths.
 *
 * Pinned invariants:
 *  - plans capture name/type/default/variadic metadata with no reflection on
 *    the hot path; non-exportable defaults (objects) are neutralised;
 *  - `self` parameters resolve to the closure scope class (named) or stay the
 *    literal `self` for anonymous scopes, where the live instance is injected;
 *  - `#[Inject]` supplies the container type for untyped parameters;
 *  - `callablePlan()` handles closures, invokable objects, array callables,
 *    `Class::method` strings and plain function names;
 *  - property plans read the `get`-hook callable from a live instance, fall
 *    back to the declared default value, and are memoized per ReflectionProperty;
 *  - gates/registries resolve parameters from hook args by name, then from the
 *    container, then defaults; unmatched variadic buckets capture nothing;
 *  - gate closures are scope-bound to the target class (private static access)
 *    and reject non-bool results;
 *  - `onUnregisterDo` cleanups are one-shot, lazy, shutdown-safe and swallow
 *    every error;
 *  - a missing plan falls back to reflection-based resolution.
 */
final class WPHookPlanProviderTest extends WplokerbjmTestCase
{
    private Container $planContainer;
    private WPHookPlanProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        PlanProviderDependency::$constructed = 0;

        $this->planContainer = new Container();
        $this->planContainer->set(
            PlanProviderDependency::class,
            autowire(PlanProviderDependency::class),
        );

        $this->provider = new WPHookPlanProvider($this->planContainer);
    }

    // ── buildCallablePlan / buildCallablePlanFromReflector ───────────

    public function testNullCallableReturnsEmptyShapedPlan(): void
    {
        self::assertSame(
            ['isStatic' => true, 'params' => []],
            $this->provider->buildCallablePlan(null),
        );
    }

    public function testPlanCapturesNameTypeDefaultAndVariadicMetadata(): void
    {
        $plan = $this->provider->buildCallablePlan(
            static function (string $search, PlanProviderDependency $dep, int $count = 5, string ...$rest): void {
            },
        );

        self::assertTrue($plan['isStatic']);
        self::assertSame([
            ['name' => 'search', 'type' => null, 'hasDefault' => false, 'default' => null, 'isVariadic' => false, 'isSelf' => false, 'allowsNull' => false],
            ['name' => 'dep', 'type' => PlanProviderDependency::class, 'hasDefault' => false, 'default' => null, 'isVariadic' => false, 'isSelf' => false, 'allowsNull' => false],
            ['name' => 'count', 'type' => null, 'hasDefault' => true, 'default' => 5, 'isVariadic' => false, 'isSelf' => false, 'allowsNull' => false],
            ['name' => 'rest', 'type' => null, 'hasDefault' => false, 'default' => null, 'isVariadic' => true, 'isSelf' => false, 'allowsNull' => false],
        ], array_map(static fn(CallableHookParams $param): array => $param->toArray(), $plan['params']));
    }

    public function testPlanCapturesSelfAndNullabilityBeforeTypeNormalisation(): void
    {
        $plan = $this->provider->buildCallablePlan(
            static function (?self $nullableOwner, self $requiredOwner, ?string $maybe = null): void {
            },
        );

        [$nullableSelf, $requiredSelf, $nullableString] = $plan['params'];

        self::assertTrue($nullableSelf->isSelf);
        self::assertTrue($nullableSelf->allowsNull);
        self::assertSame(self::class, $nullableSelf->type, 'self normalises to the declaring class for non-anonymous scopes.');

        self::assertTrue($requiredSelf->isSelf);
        self::assertFalse($requiredSelf->allowsNull);

        self::assertFalse($nullableString->isSelf);
        self::assertTrue($nullableString->allowsNull);
    }

    public function testOwnerOptionalProbeReceivesNullForNullableSelfAndResolvesContainerDeps(): void
    {
        $captured = [];
        $probe = function (?self $owner, PlanProviderDependency $dependency) use (&$captured): void {
            $captured = [$owner, $dependency];
        };

        $this->provider->invokeHookDebug(
            $probe,
            $this->provider->buildCallablePlan($probe),
            new \stdClass(),
            'owner-optional',
            null,
            true,
        );

        self::assertArrayHasKey(0, $captured, 'Probe must still be invoked with a null owner.');
        self::assertNull($captured[0]);
        self::assertInstanceOf(PlanProviderDependency::class, $captured[1] ?? null);
    }

    public function testOwnerOptionalProbeSkipsNonNullableSelfInsteadOfConstructingOwner(): void
    {
        $called = false;
        $probe = function (self $owner) use (&$called): void {
            $called = true;
        };

        $this->provider->invokeHookDebug(
            $probe,
            $this->provider->buildCallablePlan($probe),
            new \stdClass(),
            'owner-optional-non-nullable',
            null,
            true,
        );

        self::assertFalse($called, 'A non-nullable self must never be fabricated from the container.');
    }

    public function testOwnerOptionalProbeReceivesTheLiveOwnerWhenPresent(): void
    {
        $captured = null;
        $probe = function (?self $owner) use (&$captured): void {
            $captured = $owner;
        };

        $this->provider->invokeHookDebug(
            $probe,
            $this->provider->buildCallablePlan($probe),
            new \stdClass(),
            'owner-optional-live',
            $this,
            true,
        );

        self::assertSame($this, $captured);
    }

    public function testPlanDistinguishesStaticAndNonStaticClosures(): void
    {
        $static = $this->provider->buildCallablePlan(static function (): void {
        });
        $instanceBound = $this->provider->buildCallablePlan(function (): void {
        });

        self::assertTrue($static['isStatic']);
        self::assertFalse($instanceBound['isStatic']);
    }

    public function testPlanNeutralisesNonExportableObjectDefaults(): void
    {
        $plan = $this->provider->buildCallablePlan(
            static function (\stdClass $conf = new \stdClass()): void {
            },
        );

        $param = $plan['params'][0];
        self::assertSame('conf', $param->name);
        self::assertSame(\stdClass::class, $param->type);
        self::assertFalse($param->hasDefault, 'object defaults are not cache-exportable');
        self::assertNull($param->default);
    }

    public function testSelfTypeResolvesToNamedScopeClass(): void
    {
        $closure = (new PlanProviderScope())->selfGate();

        $plan = $this->provider->buildCallablePlan($closure);

        self::assertSame(PlanProviderScope::class, $plan['params'][0]->type);
    }

    public function testSelfTypeInsideAnonymousScopeStaysLiteralSelf(): void
    {
        $host = new class {
            public string $flag = 'on';

            public function makeGate(): \Closure
            {
                return static fn (self $self): bool => $self->flag === 'on';
            }
        };

        $plan = $this->provider->buildCallablePlan($host->makeGate());

        self::assertSame('self', $plan['params'][0]->type);
    }

    public function testInjectAttributeSuppliesTypeForUntypedParameter(): void
    {
        $plan = $this->provider->buildCallablePlan(
            static function (#[Inject(PlanProviderDependency::class)] $service): void {
            },
        );

        self::assertSame(PlanProviderDependency::class, $plan['params'][0]->type);
    }

    public function testReflectorPlanCapturesStaticMethodSignature(): void
    {
        $plan = $this->provider->buildCallablePlanFromReflector(
            new ReflectionMethod(PlanProviderScope::class, 'staticHandler'),
        );

        self::assertTrue($plan['isStatic']);
        self::assertSame(
            ['dep', 'items'],
            array_map(static fn(CallableHookParams $param): string => $param->name, $plan['params']),
        );
        self::assertTrue($plan['params'][1]->isVariadic);
    }

    public function testReflectorPlanCapturesInstanceMethodSignature(): void
    {
        $plan = $this->provider->buildCallablePlanFromReflector(
            new ReflectionMethod(PlanProviderScope::class, 'instanceHandler'),
        );

        self::assertFalse($plan['isStatic']);
        self::assertSame(
            ['value', 'dep', 'count'],
            array_map(static fn(CallableHookParams $param): string => $param->name, $plan['params']),
        );
        self::assertTrue($plan['params'][2]->hasDefault);
        self::assertSame(7, $plan['params'][2]->default);
    }

    // ── callablePlan() ───────────────────────────────────────────────

    public function testCallablePlanForClosureMatchesBuildCallablePlan(): void
    {
        $closure = static fn (PlanProviderDependency $dep, int $n = 3): null => null;

        self::assertEquals(
            $this->provider->buildCallablePlan($closure),
            $this->provider->callablePlan($closure),
        );
    }

    public function testCallablePlanForInvokableObject(): void
    {
        $invokable = new class {
            public function __invoke(PlanProviderDependency $dep, string $suffix = '!'): void
            {
            }
        };

        $plan = $this->provider->callablePlan($invokable);

        self::assertSame(
            ['dep', 'suffix'],
            array_map(static fn(CallableHookParams $param): string => $param->name, $plan['params']),
        );
        self::assertSame(PlanProviderDependency::class, $plan['params'][0]->type);
        self::assertSame('!', $plan['params'][1]->default);
    }

    public function testCallablePlanForArrayCallable(): void
    {
        $plan = $this->provider->callablePlan([PlanProviderScope::class, 'staticHandler']);

        self::assertTrue($plan['isStatic']);
        self::assertSame(
            ['dep', 'items'],
            array_map(static fn(CallableHookParams $param): string => $param->name, $plan['params']),
        );
    }

    public function testCallablePlanForObjectBackedArrayCallable(): void
    {
        $plan = $this->provider->callablePlan([new PlanProviderScope(), 'instanceHandler']);

        self::assertFalse($plan['isStatic']);
        self::assertSame(
            ['value', 'dep', 'count'],
            array_map(static fn(CallableHookParams $param): string => $param->name, $plan['params']),
        );
    }

    public function testCallablePlanForClassMethodString(): void
    {
        $plan = $this->provider->callablePlan(PlanProviderScope::class . '::staticHandler');

        self::assertSame(
            ['dep', 'items'],
            array_map(static fn(CallableHookParams $param): string => $param->name, $plan['params']),
        );
    }

    public function testCallablePlanForPlainFunctionString(): void
    {
        $plan = $this->provider->callablePlan('strlen');

        self::assertSame(
            ['string'],
            array_map(static fn(CallableHookParams $param): string => $param->name, $plan['params']),
        );
        self::assertNull($plan['params'][0]->type);
    }

    public function testCallablePlanForNonCallableReturnsEmptyPlan(): void
    {
        self::assertSame(['isStatic' => true, 'params' => []], $this->provider->callablePlan(42));
        self::assertSame(['isStatic' => true, 'params' => []], $this->provider->callablePlan('NoSuchClass::method'));
        self::assertSame(['isStatic' => true, 'params' => []], $this->provider->callablePlan([PlanProviderScope::class, 'missing']));
    }

    // ── extractPropertyCallablePlan / ParamNames ────────────────────

    public function testPropertyHookPlanReadsCallableFromTheGetHook(): void
    {
        $service = new class {
            public mixed $handler {
                get => static fn (string $name, PlanProviderDependency $dep, bool $flag = true): mixed => null;
            }
        };

        $plan = $this->provider->extractPropertyCallablePlan(
            new ReflectionProperty($service, 'handler'),
            $service,
        );

        self::assertSame(
            ['name', 'dep', 'flag'],
            array_map(static fn(CallableHookParams $param): string => $param->name, $plan['params']),
        );
        self::assertSame(PlanProviderDependency::class, $plan['params'][1]->type);
        self::assertTrue($plan['params'][2]->hasDefault);
        self::assertTrue($plan['params'][2]->default);
    }

    public function testPropertyHookPlanWithoutInstanceReturnsEmptyPlan(): void
    {
        $service = new class {
            public mixed $handler {
                get => static fn (string $name): mixed => null;
            }
        };

        self::assertSame(
            ['isStatic' => true, 'params' => []],
            $this->provider->extractPropertyCallablePlan(new ReflectionProperty($service, 'handler')),
        );
    }

    public function testPropertyWithoutHookOrCallableDefaultReturnsEmptyPlan(): void
    {
        $service = new class {
            public mixed $handler = null;
        };

        self::assertSame(
            ['isStatic' => true, 'params' => []],
            $this->provider->extractPropertyCallablePlan(new ReflectionProperty($service, 'handler'), $service),
        );
    }

    public function testPropertyParamNamesMirrorThePropertyPlan(): void
    {
        $service = new class {
            public mixed $handler {
                get => static fn (string $first, int $second = 2): mixed => null;
            }
        };
        $property = new ReflectionProperty($service, 'handler');

        self::assertSame(['first', 'second'], $this->provider->extractPropertyCallableParamNames($property, $service));
        self::assertSame(
            array_map(
                static fn(CallableHookParams $param): string => $param->name,
                $this->provider->extractPropertyCallablePlan($property, $service)['params'],
            ),
            $this->provider->extractPropertyCallableParamNames($property, $service),
        );
    }

    public function testPropertyPlanIsMemoizedPerReflectionPropertyObject(): void
    {
        $service = new class {
            public int $reads = 0;

            public mixed $handler {
                get {
                    $this->reads++;

                    return static fn (string $name): mixed => null;
                }
            }
        };
        $property = new ReflectionProperty($service, 'handler');

        $first = $this->provider->extractPropertyCallablePlan($property, $service);
        $second = $this->provider->extractPropertyCallablePlan($property, $service);

        self::assertSame(1, $service->reads, 'the get hook must run only once per ReflectionProperty instance');
        self::assertSame($first, $second);
    }

    // ── resolveHookName() ───────────────────────────────────────────

    public function testResolveHookNameReturnsStringHookUnchanged(): void
    {
        self::assertSame('init', $this->provider->resolveHookName('init'));
    }

    public function testResolveHookNameInvokesClosureAndReturnsItsString(): void
    {
        self::assertSame('resolved_hook', $this->provider->resolveHookName(static fn (): string => 'resolved_hook'));
    }

    public function testResolveHookNameResolvesContainerDependency(): void
    {
        $seen = null;
        $closure = static function (PlanProviderDependency $dep) use (&$seen): string {
            $seen = $dep;

            return 'hook_from_' . $dep->marker;
        };

        $name = $this->provider->resolveHookName($closure, $this->provider->buildCallablePlan($closure));

        self::assertSame('hook_from_dep', $name);
        self::assertInstanceOf(PlanProviderDependency::class, $seen);
    }

    public function testResolveHookNameRejectsNonStringResult(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('must return string');

        $this->provider->resolveHookName(static fn (): int => 5, [], 'non_string_hook');
    }

    public function testResolveHookNameInjectsSelfTypedInstanceTarget(): void
    {
        $host = new class {
            public function makeHookName(): \Closure
            {
                return static fn (self $self): string => $self::class;
            }
        };
        $closure = $host->makeHookName();

        self::assertSame(
            $host::class,
            $this->provider->resolveHookName($closure, $this->provider->buildCallablePlan($closure), 'self_hook', $host),
        );
    }

    // ── evaluateExecuteIf() ─────────────────────────────────────────

    public function testExecuteIfNullGateAllowsWithoutInvocation(): void
    {
        self::assertTrue($this->provider->evaluateExecuteIf(null, []));
    }

    public function testExecuteIfZeroParameterGateShortCircuits(): void
    {
        self::assertTrue($this->provider->evaluateExecuteIf(static fn (): bool => true, [], 'allow'));
        self::assertFalse($this->provider->evaluateExecuteIf(static fn (): bool => false, [], 'deny'));
    }

    public function testExecuteIfRejectsNonBoolResult(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('must return bool');

        $this->provider->evaluateExecuteIf(static fn (): string => 'yes', [], 'bad_gate');
    }

    public function testExecuteIfInvokesNonBoolZeroParameterGateOnlyOnce(): void
    {
        $calls = 0;
        $gate = static function () use (&$calls): string {
            $calls++;

            return 'yes';
        };

        try {
            $this->provider->evaluateExecuteIf($gate, [], 'single_invocation');
        } catch (\RuntimeException) {
            // expected
        }

        self::assertSame(1, $calls, 'a rejected gate must not be invoked a second time');
    }

    public function testExecuteIfResolvesContainerDependency(): void
    {
        self::assertTrue($this->provider->evaluateExecuteIf(
            static fn (PlanProviderDependency $dep): bool => $dep->marker === 'dep',
            $this->provider->buildCallablePlan(static fn (PlanProviderDependency $dep): bool => $dep->marker === 'dep'),
            'dep_gate',
        ));
    }

    public function testExecuteIfInjectsHookArgsByName(): void
    {
        $closure = static fn (string $search, int $page = 1): bool => $search === 'needle' && $page === 2;

        self::assertTrue($this->provider->evaluateExecuteIf(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'named_args',
            null,
            ['search' => 'needle', 'page' => 2],
        ));
    }

    public function testExecuteIfUsesParameterDefaultsForMissingHookArgs(): void
    {
        $closure = static fn (string $search, int $page = 1): bool => $search === 'needle' && $page === 1;

        self::assertTrue($this->provider->evaluateExecuteIf(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'default_arg',
            null,
            ['search' => 'needle'],
        ));
    }

    public function testExecuteIfSplatsMatchingVariadicBucket(): void
    {
        $closure = static function (string $first, string ...$rest): bool {
            return $first === 'a' && $rest === ['b', 'c'];
        };

        self::assertTrue($this->provider->evaluateExecuteIf(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'variadic_match',
            null,
            ['first' => 'a', 'rest' => ['b', 'c']],
            'rest',
        ));
    }

    public function testExecuteIfUnmatchedVariadicCapturesNothing(): void
    {
        $closure = static function (string $first, string ...$rest): bool {
            return $first === 'a' && $rest === [];
        };

        self::assertTrue($this->provider->evaluateExecuteIf(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'variadic_unmatched',
            null,
            ['first' => 'a', 'rest' => ['b', 'c']],
            'otherName',
        ));
    }

    public function testExecuteIfInjectsSelfTypedTargetInstance(): void
    {
        $host = new class {
            public string $flag = 'on';

            public function makeGate(): \Closure
            {
                return static fn (self $self): bool => $self->flag === 'on';
            }
        };
        $closure = $host->makeGate();

        self::assertTrue($this->provider->evaluateExecuteIf(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'self_gate',
            $host,
        ));
    }

    public function testExecuteIfBindsTargetScopeForPrivateStaticAccess(): void
    {
        $closure = static fn (): bool => self::$secret === 'hidden';

        self::assertTrue($this->provider->evaluateExecuteIf(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'scope_gate',
            PlanProviderScope::class,
        ));
    }

    public function testExecuteIfFallsBackToReflectionWhenPlanIsMissing(): void
    {
        self::assertTrue($this->provider->evaluateExecuteIf(
            static fn (PlanProviderDependency $dep): bool => $dep->marker === 'dep',
            [],
            'planless_gate',
        ));
    }

    public function testExecuteIfFailsWhenPlanlessParameterCannotBeResolved(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Cannot resolve parameter $unresolvable');

        $this->provider->evaluateExecuteIf(
            static fn (\Countable $unresolvable): bool => true,
            [],
            'planless_unresolvable',
        );
    }

    public function testPlanlessSelfTypedGateWithoutScopeFailsGracefully(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Cannot resolve parameter $self');

        $this->provider->evaluateExecuteIf(plan_provider_scope_less_self_gate(), [], 'scope_less_self');
    }

    // ── evaluateRegistrationGate() ──────────────────────────────────

    public function testRegistrationGateNullAllows(): void
    {
        self::assertTrue($this->provider->evaluateRegistrationGate(null, []));
    }

    public function testRegistrationGateZeroParameterShortCircuits(): void
    {
        self::assertTrue($this->provider->evaluateRegistrationGate(static fn (): bool => true, [], 'allow'));
        self::assertFalse($this->provider->evaluateRegistrationGate(static fn (): bool => false, [], 'deny'));
    }

    public function testRegistrationGateRejectsNonBoolResult(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('registerIf gate for bad_register must return bool');

        $this->provider->evaluateRegistrationGate(static fn (): int => 1, [], 'bad_register');
    }

    public function testRegistrationGateInvokesNonBoolZeroParameterGateOnlyOnce(): void
    {
        $calls = 0;
        $gate = static function () use (&$calls): int {
            $calls++;

            return 1;
        };

        try {
            $this->provider->evaluateRegistrationGate($gate, [], 'single_invocation');
        } catch (\RuntimeException) {
            // expected
        }

        self::assertSame(1, $calls, 'a rejected gate must not be invoked a second time');
    }

    public function testRegistrationGateResolvesContainerDependency(): void
    {
        $closure = static fn (PlanProviderDependency $dep): bool => $dep->marker === 'dep';

        self::assertTrue($this->provider->evaluateRegistrationGate(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'dep_register',
        ));
    }

    public function testRegistrationGateInjectsSelfTypedTargetInstance(): void
    {
        $host = new class {
            public string $flag = 'on';

            public function makeGate(): \Closure
            {
                return static fn (self $self): bool => $self->flag === 'on';
            }
        };
        $closure = $host->makeGate();

        self::assertTrue($this->provider->evaluateRegistrationGate(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'self_register',
            $host,
        ));
    }

    public function testRegistrationGateBindsTargetScopeForPrivateStaticAccess(): void
    {
        $closure = static fn (): bool => self::$secret === 'hidden';

        self::assertTrue($this->provider->evaluateRegistrationGate(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'scope_register',
            PlanProviderScope::class,
        ));
    }

    // ── resolveTagCallable() ────────────────────────────────────────

    public function testResolveTagCallableReturnsArrayFromClosure(): void
    {
        $closure = static fn (): array => ['lowongan', 'graphql'];

        self::assertSame(
            ['lowongan', 'graphql'],
            $this->provider->resolveTagCallable($closure, $this->provider->buildCallablePlan($closure), 'tag'),
        );
    }

    public function testResolveTagCallableResolvesContainerDependency(): void
    {
        $closure = static fn (PlanProviderDependency $dep): array => [$dep->marker];

        self::assertSame(
            ['dep'],
            $this->provider->resolveTagCallable($closure, $this->provider->buildCallablePlan($closure), 'tag_dep'),
        );
    }

    public function testResolveTagCallableRejectsNonArrayResult(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Tag callable for bad_tag must return array');

        $this->provider->resolveTagCallable(static fn (): string => 'nope', [], 'bad_tag');
    }

    public function testResolveTagCallableBindsTargetScopeForPrivateStaticAccess(): void
    {
        $closure = static fn (): array => [self::$secret];

        self::assertSame(
            ['hidden'],
            $this->provider->resolveTagCallable(
                $closure,
                $this->provider->buildCallablePlan($closure),
                'scope_tag',
                PlanProviderScope::class,
            ),
        );
    }

    // ── resolveOnUnregisterDo() ─────────────────────────────────────

    public function testResolveOnUnregisterDoReturnsNullForNullCleanup(): void
    {
        self::assertNull($this->provider->resolveOnUnregisterDo());
    }

    public function testResolveOnUnregisterDoRunsExactlyOnce(): void
    {
        $calls = 0;
        $cleanup = $this->provider->resolveOnUnregisterDo(
            static function () use (&$calls): void {
                $calls++;
            },
            [],
            'once_cleanup',
        );

        self::assertNotNull($cleanup);
        $cleanup();
        $cleanup();

        self::assertSame(1, $calls);
    }

    public function testResolveOnUnregisterDoResolvesDependenciesLazily(): void
    {
        $closure = static fn (PlanProviderDependency $dep): string => $dep->marker;

        $cleanup = $this->provider->resolveOnUnregisterDo(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'lazy_cleanup',
        );

        self::assertSame(0, PlanProviderDependency::$constructed, 'resolving the cleanup must not construct dependencies');

        self::assertSame('dep', $cleanup());
        self::assertSame(1, PlanProviderDependency::$constructed);
    }

    public function testResolveOnUnregisterDoSkipsWhenShutdownAlreadyRan(): void
    {
        $calls = 0;
        $cleanup = $this->provider->resolveOnUnregisterDo(
            static function () use (&$calls): void {
                $calls++;
            },
            [],
            'shutdown_guard',
        );

        \do_action('shutdown');

        self::assertNull($cleanup());
        self::assertSame(0, $calls);
    }

    public function testResolveOnUnregisterDoSkipsWhileShutdownIsDispatching(): void
    {
        $calls = 0;
        $cleanup = $this->provider->resolveOnUnregisterDo(
            static function () use (&$calls): void {
                $calls++;
            },
            [],
            'shutdown_dispatch_guard',
        );

        \add_action('shutdown', static function () use ($cleanup): void {
            $cleanup();
        }, 10);
        \do_action('shutdown');

        self::assertSame(0, $calls);
    }

    public function testResolveOnUnregisterDoSwallowsThrowingCleanup(): void
    {
        $cleanup = $this->provider->resolveOnUnregisterDo(
            static function (): void {
                throw new \RuntimeException('cleanup boom');
            },
            [],
            'throwing_cleanup',
        );

        self::assertNull($cleanup());
    }

    public function testResolveOnUnregisterDoResolvesSelfTypedWeakReferenceOwner(): void
    {
        $host = new class {
            public int $runs = 0;

            public function makeCleanup(): \Closure
            {
                return static function (self $self): void {
                    $self->runs++;
                };
            }
        };
        $closure = $host->makeCleanup();

        $cleanup = $this->provider->resolveOnUnregisterDo(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'weak_owner',
            \WeakReference::create($host),
        );

        $cleanup();

        self::assertSame(1, $host->runs);
    }

    public function testResolveOnUnregisterDoIsSafeAfterOwnerIsGarbageCollected(): void
    {
        $host = new class {
            public function makeCleanup(): \Closure
            {
                return static function (self $self): void {
                };
            }
        };
        $closure = $host->makeCleanup();
        $plan = $this->provider->buildCallablePlan($closure);
        $reference = \WeakReference::create($host);

        unset($host);
        self::assertNull($reference->get());

        $cleanup = $this->provider->resolveOnUnregisterDo($closure, $plan, 'dead_owner', $reference);

        self::assertNull($cleanup(), 'a dead owner must never make the destructor path throw');
    }

    public function testResolveOnUnregisterDoBindsTargetScopeForPrivateStaticAccess(): void
    {
        $closure = static fn (): bool => self::$secret === 'hidden';

        $cleanup = $this->provider->resolveOnUnregisterDo(
            $closure,
            $this->provider->buildCallablePlan($closure),
            'scope_cleanup',
            PlanProviderScope::class,
        );

        self::assertTrue($cleanup());
    }
}

/**
 * A `self`-typed gate defined at FILE scope (no class scope) — exercises the
 * planless fallback guard, which must fail with a RuntimeException instead of
 * dereferencing a null scope class.
 */
function plan_provider_scope_less_self_gate(): \Closure
{
    return static function (self $self): bool {
        return true;
    };
}