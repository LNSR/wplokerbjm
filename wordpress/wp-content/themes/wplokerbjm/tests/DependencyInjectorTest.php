<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use DI\Attribute\Inject;
use Psr\Container\ContainerInterface;
use RuntimeException;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract\AsChildClass;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyInjector;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

final class DependencyInjectorTest extends WplokerbjmTestCase
{
    /** @var list<string> */
    private array $cacheFiles = [];

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

        $setters = (new \ReflectionProperty(DependencyInjector::class, 'setters'))->getValue($injector);
        $this->assertCount(1, $setters);
    }

    public function testRejectsNonAsChildClassTarget(): void
    {
        $this->expectException(\TypeError::class);

        $this->injector($this->containerReturning([]))->injectOn(new \stdClass());
    }

    public function testRejectsNamedAsChildClassTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('only accepts anonymous classes');

        $this->injector($this->containerReturning([]))->injectOn(new NamedDependencyChild('Parent', 'child'));
    }

    public function testRejectsStaticPropertyInjection(): void
    {
        $target = new class (self::class, 'static') extends AsChildClass {
            #[Inject]
            private static object $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Static property injection is not supported');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsReadonlyPropertyInjection(): void
    {
        $target = new class (self::class, 'readonly') extends AsChildClass {
            #[Inject]
            private readonly object $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Readonly property injection is not supported');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsUntypedBuiltinPropertyWithoutExplicitEntry(): void
    {
        $target = new class (self::class, 'unsupported') extends AsChildClass {
            #[Inject]
            private string $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a class/interface type or an explicit');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testPropagatesUnresolvedDependencyFailure(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willThrowException(new RuntimeException('missing dependency'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing dependency');

        $this->injector($container)->injectOn($this->typedChild());
    }

    public function testInjectsArrayCallableAsBoundClosure(): void
    {
        $provider = new CallableProvider();
        $container = $this->containerReturning([CallableProvider::class => $provider]);
        $target = new class (self::class, 'callable') extends AsChildClass {
            #[Inject([CallableProvider::class, 'secret'])]
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
            #[Inject([CallableProvider::class, 'publicValue'])]
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
            #[Inject([CallableProvider::class, 'secret'])]
            private object $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('\\Closure-typed');

        $this->injector($this->containerReturning([]))->injectOn($target);
    }

    public function testRejectsArrayCallableWithUnknownMethod(): void
    {
        $target = new class (self::class, 'bad-method') extends AsChildClass {
            #[Inject([CallableProvider::class, 'nope'])]
            private \Closure $dependency;
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

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

    private function injector(ContainerInterface $container, ?string $cacheFile = null): DependencyInjector
    {
        return new DependencyInjector($container, $cacheFile ?? $this->newCacheFile());
    }

    private function newCacheFile(): string
    {
        $cacheFile =  __DIR__ . '/cache/' . bin2hex(random_bytes(8)) . '.php';
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
    private function secret(int $value): int
    {
        return $value * 2;
    }

    public function publicValue(int $value): int
    {
        return $value + 1;
    }
}
