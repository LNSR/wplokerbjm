<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Override;
use Psr\Container\ContainerInterface;
use DI\Container;
use DI\ContainerBuilder;
use RuntimeException;
use WPLokerBJM\Core\Container\Attributes\Inject;
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
        $container = $this->containerReturning(['active.plugins' => $plugins]);
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
        $firstTarget = $this->typedChild();
        $this->injector($this->containerReturning([\stdClass::class => $firstService]), $cacheFile)
            ->injectOn($firstTarget);

        $secondService = new \stdClass();
        $secondTarget = $this->typedChild();
        $this->injector($this->containerReturning([\stdClass::class => $secondService]), $cacheFile)
            ->injectOn($secondTarget);

        $cachedPlans = require $cacheFile;
        $this->assertIsArray($cachedPlans);
        $this->assertCount(1, $cachedPlans);
        $this->assertSame($secondService, $secondTarget->dependency());
    }

    public function testReusesBoundSetterForRepeatedInjection(): void
    {
        $cacheFile = $this->newCacheFile();
        $injector = $this->injector($this->containerReturning([
            \stdClass::class => new \stdClass(),
        ]), $cacheFile);

        $injector->injectOn($this->typedChild());
        $injector->injectOn($this->typedChild());
        $bind = \Closure::bind(static function(DependencyInjector $injector) {
            return $injector->scopeAccessFactory->setters;
        }, null, $injector);
        $setters = $bind($injector);
        $this->assertCount(1, $setters);
    }

    public function testRejectsNamedAsChildClassTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('only accepts anonymous classes');

        $this->injector($this->containerReturning([]))->injectOn(new NamedDependencyChild('Parent', 'child'));
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
        $container = $this->containerReturning([CallableProvider::class => $provider]);
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
        $container = $this->containerReturning([CallableProvider::class => $provider]);
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
        $container = $this->containerReturning([CallableProvider::class => $provider]);
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
        $container = $this->containerReturning([CallableProvider::class => $provider]);
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
        $container = $this->containerReturning([CallableProvider::class => $provider]);
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

    public function testRejectsLazyOnPropertyCallable(): void
    {
        $target = new class (self::class, 'bad-lazy-prop') extends AsChildClass {
            #[Inject([CallableProvider::class, 'publicClosure'], lazy: true)]
            private \Closure $factory;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('lazy flag is only valid with method callables');

        $this->injector($this->containerReturning([]))->injectOn($target);
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
        $container = $this->containerReturning([CallableProvider::class => $provider]);
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
        $container = $this->containerReturning([CallableProvider::class => $provider]);
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
        $container = $this->containerReturning([CallableProvider::class => $provider]);
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
}
