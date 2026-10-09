<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Override;
use Psr\Container\ContainerInterface;
use DI\Container;
use DI\ContainerBuilder;
use RuntimeException;
use WPLokerBJM\Core\Container\Attributes\{Inject, Injectable};
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract\AsChildClass;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\{DependencyInjector, PlanCache, PlanCompiler, ScopeAccessFactory};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

final class DependencyInjectorTest extends WplokerbjmTestCase
{
    /** @var list<string> */
    private array $cacheFiles = [];

    private function injector(ContainerInterface $container, ?string $cacheFile = null): DependencyInjector
    {
        return new DependencyInjector($container, new ScopeAccessFactory(), new PlanCache($cacheFile ?? $this->newCacheFile()), new PlanCompiler());
    }

    public function testInjectsPrivateTypedPropertyInAnonymousChildScope(): void
    {
        $service = new \stdClass();
        $container = $this->containerReturning([\stdClass::class => $service]);
        $target = $this->typedChild();

        $result = $this->injector($container)->injectOn($target);

        $this->assertSame($target, $result);
        $this->assertSame($service, $target->dependency());
    }

    public function testInjectsExplicitContainerEntry(): void
    {
        $plugins = ['plugin-a/plugin-a.php'];
        $scopeAccessFactory = new ScopeAccessFactory();
        $container = $this->containerReturning(['active.plugins' => $plugins, ScopeAccessFactory::class => $scopeAccessFactory]);
        $target = new class (self::class, 'plugins') extends AsChildClass {
            #[Inject('active.plugins')]
            private array $plugins;

            public function plugins(): array
            {
                return $this->plugins;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame($plugins, $target->plugins());
    }

    public function testCompiledPlanIsLoadedByAnotherInjector(): void
    {
        $cacheFile = $this->newCacheFile();
        $firstService = new \stdClass();
        $scopeAccessFactory = new ScopeAccessFactory();
        $firstTarget = $this->typedChild();
        $this->injector($this->containerReturning([\stdClass::class => $firstService, ScopeAccessFactory::class => $scopeAccessFactory]), $cacheFile)
            ->injectOn($firstTarget);

        $secondService = new \stdClass();
        $secondTarget = $this->typedChild();
        $this->injector($this->containerReturning([\stdClass::class => $secondService, ScopeAccessFactory::class => $scopeAccessFactory]), $cacheFile)
            ->injectOn($secondTarget);

        $cachedPlans = require $cacheFile;
        $this->assertIsArray($cachedPlans);
        $this->assertCount(1, $cachedPlans);
        $this->assertSame($secondService, $secondTarget->dependency());
    }

    public function testReusesBoundSetterForRepeatedInjection(): void
    {
        $cacheFile = $this->newCacheFile();
        $scopeAccessFactory = new ScopeAccessFactory();
        $injector = $this->injector($this->containerReturning([
            \stdClass::class => new \stdClass(),
            ScopeAccessFactory::class => $scopeAccessFactory,
        ]), $cacheFile);

        $injector->injectOn($this->typedChild());
        $injector->injectOn($this->typedChild());
        $setters = $injector->cachedSetterClosure;
        $this->assertCount(1, $setters);
    }

    public function testRejectsStaticPropertyInjection(): void
    {
        $target = new class (self::class, 'static') extends AsChildClass {
            #[Inject]
            private static object $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Static property injection is not supported');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsReadonlyPropertyInjection(): void
    {
        $target = new class (self::class, 'readonly') extends AsChildClass {
            #[Inject]
            private readonly object $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Readonly property injection is not supported');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsUntypedBuiltinPropertyWithoutExplicitEntry(): void
    {
        $target = new class (self::class, 'unsupported') extends AsChildClass {
            #[Inject]
            private string $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('needs a class/interface type or an explicit');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testPropagatesUnresolvedDependencyFailure(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willThrowException(new RuntimeException('missing dependency'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('missing dependency');

        $this->injector($container)->injectOn($this->typedChild());
    }

    public function testInjectsArrayCallableAsBoundClosure(): void
    {
        $provider = new CallableProvider();
        $scopeAccessFactory = new ScopeAccessFactory();
        $container = $this->containerReturning([CallableProvider::class => $provider, ScopeAccessFactory::class => $scopeAccessFactory]);
        $target = new class (self::class, 'callable') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secret'], lazy: true)]
            private \Closure $secret;

            public function secret(int $value): int
            {
                return ($this->secret)($value);
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(42, $target->secret(21));
    }

    public function testInjectsArrayCallablePublicMethod(): void
    {
        $provider = new CallableProvider();
        $scopeAccessFactory = new ScopeAccessFactory();
        $container = $this->containerReturning([CallableProvider::class => $provider, ScopeAccessFactory::class => $scopeAccessFactory]);
        $target = new class (self::class, 'callable-public') extends AsChildClass {
            #[Inject([CallableProvider::class, 'publicValue'], lazy: true)]
            private \Closure $publicValue;

            public function publicValue(int $value): int
            {
                return ($this->publicValue)($value);
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(6, $target->publicValue(5));
    }

    public function testRejectsArrayCallableOnNonClosureProperty(): void
    {
        $target = new class (self::class, 'bad-callable') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secret'], lazy: true)]
            private object $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('\\Closure-typed');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsArrayCallableWithUnknownMethod(): void
    {
        $target = new class (self::class, 'bad-method') extends AsChildClass {
            #[Inject([CallableProvider::class, 'nope'])]
            private \Closure $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('does not exist as a method or property');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testInjectsClosureFromPublicProperty(): void
    {
        $provider = new CallableProvider();
        $scopeAccessFactory = new ScopeAccessFactory();
        $container = $this->containerReturning([CallableProvider::class => $provider, ScopeAccessFactory::class => $scopeAccessFactory]);
        $target = new class (self::class, 'prop-public') extends AsChildClass {
            #[Inject([CallableProvider::class, 'publicClosure'])]
            private \Closure $factory;

            public function factory(int $value): int
            {
                return ($this->factory)($value);
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(1005, $target->factory(5));
    }

    public function testInjectsClosureFromPrivateProperty(): void
    {
        $provider = new CallableProvider();
        $scopeAccessFactory = new ScopeAccessFactory();
        $container = $this->containerReturning([CallableProvider::class => $provider, ScopeAccessFactory::class => $scopeAccessFactory]);
        $target = new class (self::class, 'prop-private') extends AsChildClass {
            #[Inject([CallableProvider::class, 'privateClosure'])]
            private \Closure $factory;

            public function factory(int $value): int
            {
                return ($this->factory)($value);
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(2005, $target->factory(5));
    }

    public function testInjectsAnonClassFromProperty(): void
    {
        $provider = new CallableProvider();
        $scopeAccessFactory = new ScopeAccessFactory();
        $container = $this->containerReturning([CallableProvider::class => $provider, ScopeAccessFactory::class => $scopeAccessFactory]);
        $target = new class (self::class, 'prop-child') extends AsChildClass {
            #[Inject([CallableProvider::class, 'publicChild'])]
            private AsChildClass $child;

            public function child(): AsChildClass
            {
                return $this->child;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame('hello', $target->child()->greet());
    }

    public function testRejectsPropertyTargetTypeMismatch(): void
    {
        $target = new class (self::class, 'bad-prop-type') extends AsChildClass {
            #[Inject([CallableProvider::class, 'publicChild'])]
            private \stdClass $child;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('does not match property type');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testInjectsArrayCallableReturnValue(): void
    {
        $provider = new CallableProvider();
        $scopeAccessFactory = new ScopeAccessFactory();
        $container = $this->containerReturning([CallableProvider::class => $provider, ScopeAccessFactory::class => $scopeAccessFactory]);
        $target = new class (self::class, 'callable-value') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secretValue'])]
            private int $secretValue;

            public function secretValue(): int
            {
                return $this->secretValue;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(42, $target->secretValue());
    }

    public function testInjectsClosureReturnedByMethod(): void
    {
        $provider = new CallableProvider();
        $scopeAccessFactory = new ScopeAccessFactory();
        $container = $this->containerReturning([CallableProvider::class => $provider, ScopeAccessFactory::class => $scopeAccessFactory]);
        $target = new class (self::class, 'callable-closure') extends AsChildClass {
            #[Inject([CallableProvider::class, 'closureFactory'])]
            private \Closure $factory;

            public function factory(int $value): int
            {
                return ($this->factory)($value);
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(105, $target->factory(5));
    }

    public function testInjectsAnonClassReturnedByMethod(): void
    {
        $provider = new CallableProvider();
        $scopeAccessFactory = new ScopeAccessFactory();
        $container = $this->containerReturning([CallableProvider::class => $provider, ScopeAccessFactory::class => $scopeAccessFactory]);
        $target = new class (self::class, 'callable-child') extends AsChildClass {
            #[Inject([CallableProvider::class, 'childInstance'])]
            private AsChildClass $child;

            public function child(): AsChildClass
            {
                return $this->child;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame('hello', $target->child()->greet());
    }

    public function testRejectsValueTypeMismatch(): void
    {
        $target = new class (self::class, 'bad-value-type') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secretValue'])]
            private string $secretValue;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('does not match property type');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsVoidReturnValueInjection(): void
    {
        $target = new class (self::class, 'bad-void') extends AsChildClass {
            #[Inject([CallableProvider::class, 'nothing'])]
            private mixed $nothing;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('void/never');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsRequiredParamValueInjection(): void
    {
        $target = new class (self::class, 'bad-params') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secret'])]
            private int $secret;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('zero-argument method');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsLazyOnStringEntry(): void
    {
        $target = new class (self::class, 'bad-lazy-string') extends AsChildClass {
            #[Inject('active.plugins', lazy: true)]
            private \Closure $plugins;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('lazy flag is only valid with an array callable');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsEmptyIdentifier(): void
    {
        $target = new class (self::class, '') extends AsChildClass {
            #[Inject]
            private \stdClass $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('non-empty identifier');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testReturnsTargetUnchangedWhenNoInjectableProperties(): void
    {
        $target = new class (self::class, 'noop') extends AsChildClass {
            public function ping(): string
            {
                return 'pong';
            }
        };
        $injector = $this->injector($this->containerReturning([]));

        $result = $injector->injectOn($target);

        $this->assertSame($target, $result);
        $this->assertCount(0, $injector->cachedSetterClosure);
    }

    public function testInjectsNullableTypedPropertyByTypeName(): void
    {
        $service = new \stdClass();
        $container = $this->containerReturning([\stdClass::class => $service]);
        $target = new class (self::class, 'nullable') extends AsChildClass {
            #[Inject]
            private ?\stdClass $dependency;

            public function dependency(): ?\stdClass
            {
                return $this->dependency;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame($service, $target->dependency());
    }

    public function testInjectsInterfaceTypedPropertyByTypeName(): void
    {
        $service = new \ArrayObject();
        $container = $this->containerReturning([\ArrayAccess::class => $service]);
        $target = new class (self::class, 'iface-type') extends AsChildClass {
            #[Inject]
            private \ArrayAccess $dependency;

            public function dependency(): \ArrayAccess
            {
                return $this->dependency;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame($service, $target->dependency());
    }

    public function testInjectsMultipleProperties(): void
    {
        $service = new \stdClass();
        $plugins = ['plugin-a/plugin-a.php'];
        $container = $this->containerReturning([\stdClass::class => $service, 'plugins' => $plugins]);
        $target = new class (self::class, 'multi') extends AsChildClass {
            #[Inject]
            private \stdClass $alpha;

            #[Inject('plugins')]
            private array $zeta;

            public function alpha(): \stdClass
            {
                return $this->alpha;
            }

            public function zeta(): array
            {
                return $this->zeta;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame($service, $target->alpha());
        $this->assertSame($plugins, $target->zeta());
    }

    public function testCompilesSeparatePlansPerIdentifier(): void
    {
        $cacheFile = $this->newCacheFile();
        $service = new \stdClass();
        $injector = $this->injector($this->containerReturning([\stdClass::class => $service]), $cacheFile);
        $first = $this->typedChild('first');
        $second = $this->typedChild('second');

        $injector->injectOn($first);
        $injector->injectOn($second);

        $this->assertSame($service, $first->dependency());
        $this->assertSame($service, $second->dependency());
        $this->assertCount(2, $injector->cachedSetterClosure);

        unset($injector);
        $this->assertCount(2, require $cacheFile);
    }

    public function testSharesPlanAcrossClassDefinitionsWithSameIdentity(): void
    {
        $cacheFile = $this->newCacheFile();
        $service = new \stdClass();
        $injector = $this->injector($this->containerReturning([\stdClass::class => $service]), $cacheFile);
        $first = new class (self::class, 'shared') extends AsChildClass {
            #[Inject]
            private \stdClass $dependency;

            public function dependency(): \stdClass
            {
                return $this->dependency;
            }
        };
        $second = new class (self::class, 'shared') extends AsChildClass {
            #[Inject]
            private \stdClass $dependency;

            public function dependency(): \stdClass
            {
                return $this->dependency;
            }
        };

        $injector->injectOn($first);
        $injector->injectOn($second);

        $this->assertSame($service, $first->dependency());
        $this->assertSame($service, $second->dependency());
        $this->assertCount(2, $injector->cachedSetterClosure);

        unset($injector);
        $this->assertCount(1, require $cacheFile);
    }

    public function testInjectsInheritedProtectedInjectableProperty(): void
    {
        $service = new \stdClass();
        $container = $this->containerReturning([\stdClass::class => $service]);
        $target = new class (self::class, 'protected-inherited') extends ProtectedInjectParent {};

        $this->injector($container)->injectOn($target);

        $this->assertSame($service, $target->dep());
    }

    public function testRejectsInheritedPrivateInjectableProperty(): void
    {
        $target = new class (self::class, 'private-inherited') extends PrivateInjectParent {};

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('must be declared by the child class');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsEmptyStringEntry(): void
    {
        $target = new class (self::class, 'empty-entry') extends AsChildClass {
            #[Inject('')]
            private string $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('must be a non-empty string');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsMalformedCallableEntry(): void
    {
        $target = new class (self::class, 'bad-callable-shape') extends AsChildClass {
            #[Inject([CallableProvider::class])]
            private int $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('must be a non-empty string or an array callable');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsCallableEntryWithUnknownClass(): void
    {
        $target = new class (self::class, 'unknown-class') extends AsChildClass {
            #[Inject([\Nonexistent\MissingProvider::class, 'provide'])]
            private int $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('does not exist.');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testInjectsLazyPropertyAsLiveValue(): void
    {
        $provider = new CallableProvider();
        $container = $this->containerReturning([CallableProvider::class => $provider]);
        $target = new class (self::class, 'lazy-prop-live') extends AsChildClass {
            #[Inject([CallableProvider::class, 'counter'], lazy: true)]
            private \Closure $counter;

            public function counter(): int
            {
                return ($this->counter)();
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(0, $target->counter());

        $provider->counter = 42;
        $this->assertSame(42, $target->counter());
    }

    public function testInjectsLazyPrivateProperty(): void
    {
        $provider = new CallableProvider();
        $container = $this->containerReturning([CallableProvider::class => $provider]);
        $target = new class (self::class, 'lazy-prop-private') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secretCounter'], lazy: true)]
            private \Closure $secretCounter;

            public function secretCounter(): int
            {
                return ($this->secretCounter)();
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(10, $target->secretCounter());
    }

    public function testInjectsInterfaceMethodReturnValue(): void
    {
        $provider = new GreetingProvider();
        $container = $this->containerReturning([GreetingProviderInterface::class => $provider]);
        $target = new class (self::class, 'iface-method') extends AsChildClass {
            #[Inject([GreetingProviderInterface::class, 'greeting'])]
            private string $greeting;

            public function greeting(): string
            {
                return $this->greeting;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame('hello interface', $target->greeting());
    }

    public function testInjectsLazyInterfaceMethodClosure(): void
    {
        $provider = new GreetingProvider();
        $container = $this->containerReturning([GreetingProviderInterface::class => $provider]);
        $target = new class (self::class, 'iface-lazy') extends AsChildClass {
            #[Inject([GreetingProviderInterface::class, 'greeting'], lazy: true)]
            private \Closure $greeting;

            public function greeting(): string
            {
                return ($this->greeting)();
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame('hello interface', $target->greeting());
    }

    public function testInjectsUntypedPropertyFromCallableProperty(): void
    {
        $provider = new CallableProvider();
        $container = $this->containerReturning([CallableProvider::class => $provider]);
        $target = new class (self::class, 'untyped-prop') extends AsChildClass {
            #[Inject([CallableProvider::class, 'publicClosure'])]
            private $factory;

            public function factory(int $value): int
            {
                return ($this->factory)($value);
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(1005, $target->factory(5));
    }

    public function testRejectsValueInjectionIntoUntypedProperty(): void
    {
        $target = new class (self::class, 'untyped-value') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secretValue'])]
            private $secretValue;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('requires a typed property');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testPropagatesMissingCallableProviderFailure(): void
    {
        $container = $this->containerReturning([]);
        $target = new class (self::class, 'missing-provider') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secretValue'])]
            private int $secretValue;
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Missing entry: ' . CallableProvider::class);

        $this->injector($container)->injectOn($target);
    }

    public function testInjectsClassIntoObjectTypedProperty(): void
    {
        $provider = new CallableProvider();
        $container = $this->containerReturning([CallableProvider::class => $provider]);
        $target = new class (self::class, 'object-target') extends AsChildClass {
            #[Inject([CallableProvider::class, 'publicChild'])]
            private object $child;

            public function child(): object
            {
                return $this->child;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame('hello', $target->child()->greet());
    }

    public function testInjectsIntoMixedTypedProperty(): void
    {
        $provider = new CallableProvider();
        $container = $this->containerReturning([CallableProvider::class => $provider]);
        $target = new class (self::class, 'mixed-target') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secretValue'])]
            private mixed $secretValue;

            public function secretValue(): mixed
            {
                return $this->secretValue;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(42, $target->secretValue());
    }

    public function testInjectsArrayReturnIntoIterableProperty(): void
    {
        $provider = new CallableProvider();
        $container = $this->containerReturning([CallableProvider::class => $provider]);
        $target = new class (self::class, 'iterable-target') extends AsChildClass {
            #[Inject([CallableProvider::class, 'items'])]
            private iterable $items;

            public function items(): iterable
            {
                return $this->items;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(['a', 'b'], $target->items());
    }

    public function testRejectsBuiltinIntoObjectTypedProperty(): void
    {
        $target = new class (self::class, 'builtin-object') extends AsChildClass {
            #[Inject([CallableProvider::class, 'stringValue'])]
            private object $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('does not match property type');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsNullableReturnIntoNonNullableProperty(): void
    {
        $target = new class (self::class, 'nullable-mismatch') extends AsChildClass {
            #[Inject([CallableProvider::class, 'maybeSecretValue'])]
            private int $maybeSecretValue;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('requires a nullable property');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testInjectsNullableReturnIntoNullableProperty(): void
    {
        $provider = new CallableProvider();
        $container = $this->containerReturning([CallableProvider::class => $provider]);
        $target = new class (self::class, 'nullable-ok') extends AsChildClass {
            #[Inject([CallableProvider::class, 'maybeSecretValue'])]
            private ?int $maybeSecretValue;

            public function maybeSecretValue(): ?int
            {
                return $this->maybeSecretValue;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertNull($target->maybeSecretValue());
    }

    public function testRejectsUnionTypedPropertyWithoutExplicitEntry(): void
    {
        $target = new class (self::class, 'union-no-entry') extends AsChildClass {
            #[Inject]
            private \stdClass|\ArrayObject $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('needs a class/interface type or an explicit');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testInjectsUnionReturnIntoUnionProperty(): void
    {
        $provider = new CallableProvider();
        $container = $this->containerReturning([CallableProvider::class => $provider]);
        $target = new class (self::class, 'union-ok') extends AsChildClass {
            #[Inject([CallableProvider::class, 'intOrString'])]
            private int|string $value;

            public function value(): int|string
            {
                return $this->value;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame(7, $target->value());
    }

    public function testRejectsNeverReturnValueInjection(): void
    {
        $target = new class (self::class, 'bad-never') extends AsChildClass {
            #[Inject([CallableProvider::class, 'neverReturns'])]
            private mixed $neverReturns;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('void/never');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsVirtualHookedPropertyInjection(): void
    {
        $target = new class (self::class, 'virtual') extends AsChildClass {
            #[Inject]
            private \stdClass $dependency {
                get => throw new \LogicException('never read');
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Virtual property injection is not supported');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testInjectsBackedHookedProperty(): void
    {
        $service = new \stdClass();
        $container = $this->containerReturning([\stdClass::class => $service]);
        $target = new class (self::class, 'hooked') extends AsChildClass {
            #[Inject]
            private \stdClass $dependency {
                set {
                    $this->dependency = $value;
                }
            }

            public function dependency(): \stdClass
            {
                return $this->dependency;
            }
        };

        $this->injector($container)->injectOn($target);

        $this->assertSame($service, $target->dependency());
    }

    public function testLoadsMissingCacheFileAsEmptyPlans(): void
    {
        $cache = new PlanCache($this->newCacheFile());

        $this->assertSame([], $cache->loadPlans());
    }

    public function testThrowsWhenCacheFileDoesNotReturnArray(): void
    {
        $cacheFile = $this->newCacheFile();
        $this->writeCacheFile($cacheFile, "<?php return 'not-an-array';");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('must return an array');

        (new PlanCache($cacheFile))->loadPlans();
    }

    public function testRecompilesWhenCachedPlanPropertiesAreInvalid(): void
    {
        $cacheFile = $this->newCacheFile();
        $cacheKey = self::class . '->dependency';
        $this->writeCacheFile(
            $cacheFile,
            '<?php return [' . var_export($cacheKey, true) . ' => ["properties" => [0 => new \WPLokerBJM\Core\Container\Support\InstanceDiscovery\InjectionEntryDTO("stdClass")]]];',
        );

        $service = new \stdClass();
        $target = $this->typedChild();
        $this->injector($this->containerReturning([\stdClass::class => $service]), $cacheFile)->injectOn($target);

        $this->assertSame($service, $target->dependency());
        $plans = require $cacheFile;
        $this->assertCount(1, $plans);
        $this->assertArrayHasKey('dependency', $plans[$cacheKey]['properties']);
    }

    public function testRecompilesWhenCachedEntryIsNotDTO(): void
    {
        $cacheFile = $this->newCacheFile();
        $cacheKey = self::class . '->dependency';
        $this->writeCacheFile(
            $cacheFile,
            '<?php return [' . var_export($cacheKey, true) . ' => ["properties" => ["dependency" => ["class" => "stdClass"]]]];',
        );

        $service = new \stdClass();
        $target = $this->typedChild();
        $this->injector($this->containerReturning([\stdClass::class => $service]), $cacheFile)->injectOn($target);

        $this->assertSame($service, $target->dependency());
    }

    public function testClearCachedPlansRemovesCacheFile(): void
    {
        $cacheFile = $this->newCacheFile();
        $cache = new PlanCache($cacheFile);
        $cache->writePlans(['key' => ['properties' => []]]);
        unset($cache);

        $this->assertFileExists($cacheFile);

        $cache = new PlanCache($cacheFile);
        $cache->clearCachedPlans();

        $this->assertFileDoesNotExist($cacheFile);
        $this->assertSame([], $cache->loadPlans());
    }

    public function testInjectableAttributeDefaults(): void
    {
        $injectable = new Injectable();

        $this->assertNull($injectable->lazy);
        $this->assertFalse($injectable->skip);
    }

    public function testInjectableAttributeWithFlags(): void
    {
        $injectable = new Injectable(lazy: true, skip: true);

        $this->assertTrue($injectable->lazy);
        $this->assertTrue($injectable->skip);
    }

    public function testInjectableAttributeReflection(): void
    {
        $attributes = (new \ReflectionClass(MarkedInjectableFixture::class))->getAttributes(Injectable::class);

        $this->assertCount(1, $attributes);

        $injectable = $attributes[0]->newInstance();
        $this->assertTrue($injectable->lazy);
        $this->assertFalse($injectable->skip);
    }

    public function testInjectAttributeDefaults(): void
    {
        $inject = new Inject();

        $this->assertNull($inject->name);
        $this->assertFalse($inject->lazy);
    }

    protected function tearDown(): void
    {
        foreach ($this->cacheFiles as $cacheFile) {
            if (is_file($cacheFile)) {
                unlink($cacheFile);
            }
        }

        parent::tearDown();
    }

    private function typedChild(string $identifier = 'dependency'): object
    {
        return new class (self::class, $identifier) extends AsChildClass {
            #[Inject]
            private \stdClass $dependency;

            public function dependency(): \stdClass
            {
                return $this->dependency;
            }
        };
    }

    private function newCacheFile(): string
    {
        $cacheFile = __DIR__ . '/cache/' . bin2hex(random_bytes(8)) . '.php';
        $this->cacheFiles[] = $cacheFile;

        return $cacheFile;
    }

    private function writeCacheFile(string $cacheFile, string $content): void
    {
        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0755, true);
        }

        file_put_contents($cacheFile, $content);
    }

    /** @param array<string, mixed> $services */
    private function containerReturning(array $services): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn(string $entry): mixed => $services[$entry] ?? throw new RuntimeException('Missing entry: ' . $entry),
        );

        return $container;
    }
}

final class NamedDependencyChild extends AsChildClass
{
}

interface GreetingProviderInterface
{
    public function greeting(): string;
}

final class GreetingProvider implements GreetingProviderInterface
{
    public function greeting(): string
    {
        return 'hello interface';
    }
}

class ProtectedInjectParent extends AsChildClass
{
    #[Inject]
    protected \stdClass $dep;

    public function dep(): \stdClass
    {
        return $this->dep;
    }
}

class PrivateInjectParent extends AsChildClass
{
    #[Inject]
    private \stdClass $dep;
}

#[Injectable(lazy: true)]
final class MarkedInjectableFixture
{
}

final class CallableProvider
{
    public \Closure $publicClosure;

    private \Closure $privateClosure;

    public AsChildClass $publicChild;

    public function __construct()
    {
        $this->publicClosure = static fn(int $value): int => $value + 1000;
        $this->privateClosure = static fn(int $value): int => $value + 2000;
        $this->publicChild = new class ('Provider', 'child') extends AsChildClass {
            public function greet(): string
            {
                return 'hello';
            }
        };
    }

    private function secret(int $value): int
    {
        return $value * 2;
    }

    private function secretValue(): int
    {
        return 42;
    }

    public function publicValue(int $value): int
    {
        return $value + 1;
    }

    public function closureFactory(): \Closure
    {
        return static fn(int $value): int => $value + 100;
    }

    public function childInstance(): AsChildClass
    {
        return new class ('Provider', 'child') extends AsChildClass {
            public function greet(): string
            {
                return 'hello';
            }
        };
    }

    public function nothing(): void {}

    public function stringValue(): string
    {
        return 'string';
    }

    public function maybeSecretValue(): ?int
    {
        return null;
    }

    public function items(): array
    {
        return ['a', 'b'];
    }

    public function intOrString(): int|string
    {
        return 7;
    }

    public function neverReturns(): never
    {
        throw new \RuntimeException('neverReturns() must never be invoked.');
    }

    public int $counter = 0;

    private int $secretCounter = 10;
}
