<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery;

use Brick\VarExporter\VarExporter;
use Closure;
use DI\Attribute\Inject;
use Exception;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract\AsChildClass;

/**
 * Compiles and applies property injection plans for anonymous child objects.
 *
 * Reflection is used only when a plan is missing. The runtime path resolves
 * the already-compiled entries and reuses a closure bound to the anonymous
 * child scope to write private and protected members.
 * @phpstan-type CompiledPlan array{properties: array<string, string|array{0: string, 1: string}>}
 */
final class DependencyInjector
{

    /** @var array<string, CompiledPlan>|null */
    private ?array $compiledPlans = null;

    /** @var array<string, Closure> */
    private array $setters = [];

    /** @var array<string, Closure> */
    private array $closureFactories = [];

    /** @var Closure(ContainerInterface, array{0: string, 1: string}): Closure|null */
    private ?Closure $callableResolver = null;

    private readonly string $cacheLocation;

    public function __construct(
        private readonly ContainerInterface $container,
        ?string $cacheLocation = null,
    ) {
        $this->cacheLocation = $cacheLocation ?? throw new Exception('Cache location is not set.');
    }

    /**
     * Inject all #[Inject] properties on an existing anonymous child object.
     *
     * @return AsChildClass The same target, for fluent usage.
     * @throws InvalidArgumentException When the target or injection shape is invalid.
     */
    public function injectOn(AsChildClass $target): AsChildClass
    {
        $this->assertAnonymousTarget($target);
        $cacheKey = $this->buildCacheKey($target);
        $plan = $this->getOrCompilePlan($cacheKey, $target);
        $scopeClass = \get_class($target);
        $setterKey = $cacheKey . "\0" . $scopeClass;

        $setter = $this->setters[$setterKey] ??= $this->createSetter($scopeClass);
        $setter($this->container, $target, $plan['properties'], $this->callableResolver());

        return $target;
    }

    public function clearCachedPlans(): void
    {
        if (!empty($this->cacheLocation) && file_exists($this->cacheLocation)) {
            unlink($this->cacheLocation);
        }
    }

    private function assertAnonymousTarget(AsChildClass $target): void
    {
        if (!str_contains($target::class, '@anonymous')) {
            throw new InvalidArgumentException(
                'DependencyInjector::injectOn() only accepts anonymous classes extending ' . AsChildClass::class . '.',
            );
        }

        if ($target->identifier === '') {
            throw new InvalidArgumentException('An anonymous child injection target must have a non-empty identifier.');
        }
    }

    /**
     * @return CompiledPlan
     */
    private function getOrCompilePlan(string $cacheKey, AsChildClass $target): array
    {
        $plans = $this->loadPlans();
        $cachedPlan = $plans[$cacheKey] ?? null;

        if ($this->isUsablePlan($cachedPlan)) {
            return $cachedPlan;
        }

        $reflection = new ReflectionClass($target);
        $plan = $this->discoverPlan($reflection);
        $plans[$cacheKey] = $plan;
        $this->compiledPlans = $plans;
        $this->writePlans($plans);

        return $plan;
    }

    /**
     * @return array<string, CompiledPlan>
     */
    private function loadPlans(): array
    {
        if ($this->compiledPlans !== null) {
            return $this->compiledPlans;
        }

        if (!is_file($this->cacheLocation)) {
            return $this->compiledPlans = [];
        }

        $plans = require $this->cacheLocation;
        if (!is_array($plans)) {
            throw new RuntimeException('Dependency injector cache must return an array.');
        }

        return $this->compiledPlans = $plans;
    }

    /**
     * @param ?array $plan
     */
    private function isUsablePlan(?array $plan): bool
    {
        if (!is_array($plan) || !isset($plan['properties'])) {
            return false;
        }

        foreach ($plan['properties'] as $property => $entry) {
            if (!is_string($property) || (!is_string($entry) && !$this->isCallableEntry($entry))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param ReflectionClass<AsChildClass> $reflection
     * @return array{properties: array<string, string|array{0: string, 1: string}>}
     */
    private function discoverPlan(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Inject::class);
            if ($attributes === []) {
                continue;
            }

            if (count($attributes) !== 1) {
                throw new InvalidArgumentException(
                    sprintf('Property %s::%s must have exactly one #[Inject] attribute.', $reflection->getName(), $property->getName()),
                );
            }

            $this->validateProperty($property, $reflection);
            $properties[$property->getName()] = $this->resolveDependencyEntry($property, $attributes[0]);
        }

        if ($properties === []) {
            throw new InvalidArgumentException(
                'No injectable #[Inject] properties were found on ' . $reflection->getName() . '.' . ' Parent Class: ' . $reflection->getParentClass()->getName(),
            );
        }

        ksort($properties);

        return [
            'properties' => $properties,
        ];
    }

    /**
     * @param ReflectionClass<AsChildClass> $reflection
     */
    private function validateProperty(ReflectionProperty $property, ReflectionClass $reflection): void
    {
        if ($property->isStatic()) {
            throw new InvalidArgumentException('Static property injection is not supported: ' . $property->getName() . '.');
        }

        if ($property->isReadOnly()) {
            throw new InvalidArgumentException('Readonly property injection is not supported: ' . $property->getName() . '.');
        }

        if ($property->isPrivate() && $property->getDeclaringClass()->getName() !== $reflection->getName()) {
            throw new InvalidArgumentException(
                'Private injectable properties must be declared by the anonymous child: ' . $property->getName() . '.',
            );
        }
    }

    private function resolveDependencyEntry(ReflectionProperty $property, ReflectionAttribute $attribute): string|array
    {
        $arguments = $attribute->getArguments();
        $entry = $arguments['name'] ?? $arguments[0] ?? null;

        if ($entry !== null) {
            if (is_string($entry)) {
                if ($entry === '') {
                    throw new InvalidArgumentException(
                        'The #[Inject] entry for property ' . $property->getName() . ' must be a non-empty string.',
                    );
                }

                return $entry;
            }

            if ($this->isCallableEntry($entry)) {
                $this->validateCallableEntry($property, $entry);

                return $entry;
            }

            throw new InvalidArgumentException(
                'The #[Inject] entry for property ' . $property->getName() . ' must be a non-empty string or an array callable [class, method].',
            );
        }

        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw new InvalidArgumentException(
                'Property ' . $property->getName() . ' needs a class/interface type or an explicit #[Inject] entry.',
            );
        }

        return $type->getName();
    }

    /**
     * @param mixed $entry
     */
    private function isCallableEntry(mixed $entry): bool
    {
        return is_array($entry)
            && count($entry) === 2
            && isset($entry[0], $entry[1])
            && is_string($entry[0]) && $entry[0] !== ''
            && is_string($entry[1]) && $entry[1] !== '';
    }

    /**
     * @param array{0: string, 1: string} $entry
     */
    private function validateCallableEntry(ReflectionProperty $property, array $entry): void
    {
        [$class, $method] = $entry;

        if (!class_exists($class) && !interface_exists($class)) {
            throw new InvalidArgumentException(
                'The #[Inject] callable class ' . $class . ' for property ' . $property->getName() . ' does not exist.',
            );
        }

        if (!method_exists($class, $method)) {
            throw new InvalidArgumentException(
                'The #[Inject] callable method ' . $class . '::' . $method . ' for property ' . $property->getName() . ' does not exist.',
            );
        }

        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || $type->getName() !== \Closure::class) {
            throw new InvalidArgumentException(
                'Array-callable injection requires a \\Closure-typed property: ' . $property->getName() . '.',
            );
        }
    }

    /**
     * Resolve an array callable [class, method] into a closure bound to the
     * owning instance scope so private and protected methods stay callable.
     *
     * @param array{0: string, 1: string} $entry
     */
    private function resolveCallable(ContainerInterface $container, array $entry): Closure
    {
        [$class, $method] = $entry;
        $instance = $container->get($class);
        $factoryKey = $class . '::' . $method;
        $factory = $this->closureFactories[$factoryKey] ??= $this->createCallableFactory($class, $method);

        return $factory($instance);
    }

    /**
     * @param class-string $class
     */
    private function createCallableFactory(string $class, string $method): Closure
    {
        $factory = Closure::bind(
            static function (object $instance) use ($method): Closure {
                return $instance->{$method}(...);
            },
            null,
            $class,
        );

        if (!$factory instanceof Closure) {
            throw new RuntimeException('Unable to bind callable factory for ' . $class . '::' . $method . '.');
        }

        return $factory;
    }

    private function callableResolver(): Closure
    {
        return $this->callableResolver ??= $this->resolveCallable(...);
    }

    /**
     * @param class-string $scopeClass
     * @throws RuntimeException
     * @return Closure(ContainerInterface $container, AsChildClass $target, CompiledPlan $plan, Closure $resolveCallable): void
     */
    private function createSetter(string $scopeClass): Closure
    {
        static $cb = static function (ContainerInterface $container, AsChildClass $target, array $properties, Closure $resolveCallable): void {
            foreach ($properties as $property => $entry) {
                $target->{$property} = is_array($entry)
                    ? $resolveCallable($container, $entry)
                    : $container->get($entry);
            }
        };
        $setter = Closure::bind(
            $cb,
            null,
            $scopeClass,
        );

        if (!$setter instanceof Closure) {
            throw new RuntimeException('Unable to bind the dependency injector to the anonymous child scope.');
        }

        return $setter;
    }

    private function buildCacheKey(AsChildClass $target): string
    {
        return $target->getParentClass() . '::' . $target->identifier;
    }

    /**
     * @param array<string, CompiledPlan> $plans
     */
    private function writePlans(array $plans): void
    {
        $directory = dirname($this->cacheLocation);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create dependency injector cache directory: ' . $directory . '.');
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('Dependency injector cache directory is not writable: ' . $directory . '.');
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n // Generated at " . date('Y-m-d H:i:s') . "\n" . VarExporter::export(
            $plans,
            VarExporter::ADD_RETURN | VarExporter::ADD_TYPE_HINTS | VarExporter::CLOSURE_SNAPSHOT_USES,
        );
        $temporaryFile = tempnam($directory, '.DependencyInjectorCache.');
        if ($temporaryFile === false || file_put_contents($temporaryFile, $content, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write dependency injector cache: ' . $this->cacheLocation . '.');
        }

        if (!rename($temporaryFile, $this->cacheLocation)) {
            @unlink($temporaryFile);
            throw new RuntimeException('Unable to replace dependency injector cache: ' . $this->cacheLocation . '.');
        }
    }
}
