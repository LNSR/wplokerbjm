<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery;

use Brick\VarExporter\VarExporter;
use Closure;
use Exception;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Attributes\Inject;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract\AsChildClass;

/**
 * Compiles and applies property injection plans for anonymous child objects.
 *
 * Reflection is used only when a plan is missing. The runtime path resolves
 * the already-compiled entries and reuses a closure bound to the anonymous
 * child scope to write private and protected members.
 * @phpstan-type CallableEntry array{class: string, member: string, kind: 'method'|'property', lazy: bool}
 * @phpstan-type CompiledPlan array{properties: array<string, string|CallableEntry>}
 */
final class DependencyInjector
{

    /**
     * @param ContainerInterface $container
     * @param ScopeAccessFactory $scopeAccessFactory
     * @param PlanCache $planCache
     * @param PlanCompiler $planCompiler
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ScopeAccessFactory $scopeAccessFactory,
        private readonly PlanCache $planCache,
        private readonly PlanCompiler $planCompiler,
    ) {}

    /**
     * Inject all #[Inject] properties on an existing anonymous child object.
     *
     * @return AsChildClass The same target, for fluent usage.
     * @throws InvalidArgumentException When the target or injection shape is invalid.
     */
    public function injectOn(AsChildClass $target): AsChildClass
    {
        $this->assertAnonymousTarget($target);
        $cacheKey = $this->planCache->buildCacheKey($target);
        $plan = $this->getOrCompilePlan($cacheKey, $target);
        $scopeClass = $target::class;
        $setterKey = $cacheKey . "\0" . $scopeClass;

        $setter = $this->scopeAccessFactory->createSetter($setterKey, $scopeClass);
        $setter($this->container, $target, $plan['properties'], $this->scopeAccessFactory->callableResolver());

        return $target;
    }

    /**
     * @return CompiledPlan
     */
    private function getOrCompilePlan(string $cacheKey, AsChildClass $target): array
    {
        $plans = $this->planCache->loadPlans();
        $cachedPlan = $plans[$cacheKey] ?? null;

        if ($this->planCache->isUsablePlan($cachedPlan))
            return $cachedPlan;

        $reflection = new ReflectionClass($target);
        $plan = $this->planCompiler->discoverPlan($reflection);
        $plans[$cacheKey] = $plan;
        $this->planCache->writePlans($plans);

        return $plan;
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
}
/**
 * @phpstan-import-type CallableEntry from DependencyInjector
 * @phpstan-import-type CompiledPlan from DependencyInjector
 */
class PlanCompiler
{
    /**
     * @param ReflectionClass<AsChildClass> $reflection
     * @return CompiledPlan
     */
    public function discoverPlan(ReflectionClass $reflection): array
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
        /**
         * @var array{name: string|array|null, lazy: bool} $arguments
         * @see Inject
         */
        $arguments = $attribute->getArguments();
        $entry = $arguments['name'] ?? $arguments[0] ?? null;
        $lazy = $arguments['lazy'] ?? $arguments[1] ?? false;

        if (!is_bool($lazy)) {
            throw new InvalidArgumentException(
                'The #[Inject] lazy flag for property ' . $property->getName() . ' must be a boolean.',
            );
        }

        if ($entry !== null) {
            if (is_string($entry)) {
                if ($entry === '') {
                    throw new InvalidArgumentException(
                        'The #[Inject] entry for property ' . $property->getName() . ' must be a non-empty string.',
                    );
                }

                if ($lazy) {
                    throw new InvalidArgumentException(
                        'The #[Inject] lazy flag is only valid with an array callable [class, member]: ' . $property->getName() . '.',
                    );
                }

                return $entry;
            }

            if ($this->isCallableEntry($entry)) {
                return $this->validateCallableEntry($property, $entry, $lazy);
            }

            throw new InvalidArgumentException(
                'The #[Inject] entry for property ' . $property->getName() . ' must be a non-empty string or an array callable [class, member].',
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
     * @param string|array $entry
     */
    private function isCallableEntry(string|array $entry): bool
    {
        return is_array($entry)
            && count($entry) === 2
            && isset($entry[0], $entry[1])
            && is_string($entry[0]) && $entry[0] !== ''
            && is_string($entry[1]) && $entry[1] !== '';
    }
    /**
     * @param array{class-string, string} $entry
     * @return CallableEntry
     */
    private function validateCallableEntry(ReflectionProperty $property, array $entry, bool $lazy): array
    {
        [$class, $member] = $entry;

        if (!class_exists($class) && !interface_exists($class)) {
            throw new InvalidArgumentException(
                'The #[Inject] callable class ' . $class . ' for property ' . $property->getName() . ' does not exist.',
            );
        }

        if (method_exists($class, $member)) {
            $kind = 'method';
        } elseif (property_exists($class, $member)) {
            $kind = 'property';
        } else {
            throw new InvalidArgumentException(
                'The #[Inject] callable member ' . $class . '::' . $member . ' for property ' . $property->getName() . ' does not exist as a method or property.',
            );
        }

        if ($kind === 'property') {
            if ($lazy) {
                throw new InvalidArgumentException(
                    'The #[Inject] lazy flag is only valid with method callables: ' . $property->getName() . '.',
                );
            }

            $this->validatePropertyTargetType($property, $class, $member);

            return ['class' => $class, 'member' => $member, 'kind' => $kind, 'lazy' => false];
        }

        $reflectionMethod = new ReflectionMethod($class, $member);

        if ($lazy) {
            $type = $property->getType();
            if (!$type instanceof ReflectionNamedType || $type->getName() !== \Closure::class) {
                throw new InvalidArgumentException(
                    'Lazy callable injection requires a \\Closure-typed property: ' . $property->getName() . '.',
                );
            }
        } else {
            $this->validateValueReturnType($property, $reflectionMethod);
        }

        return ['class' => $class, 'member' => $member, 'kind' => $kind, 'lazy' => $lazy];
    }

    /**
     * Property callables fetch the child property value, so the child
     * property's declared type must be assignable to the target property's
     * declared type. Untyped sides cannot be validated statically and are
     * allowed.
     */
    private function validatePropertyTargetType(ReflectionProperty $property, string $class, string $member): void
    {
        $targetType = $property->getType();
        if ($targetType === null) {
            return;
        }

        $childProperty = new ReflectionProperty($class, $member);
        $childType = $childProperty->getType();
        if ($childType === null) {
            return;
        }

        $sourceLabel = $class . '::$' . $member;

        if ($childType instanceof ReflectionNamedType) {
            $this->assertNamedTypeAssignable($childType, $targetType, $property, $sourceLabel);

            return;
        }

        if ($childType instanceof ReflectionUnionType) {
            foreach ($childType->getTypes() as $memberType) {
                if ($memberType instanceof ReflectionNamedType) {
                    $this->assertNamedTypeAssignable($memberType, $targetType, $property, $sourceLabel);
                }
            }
        }
        // intersection and other shapes: allow (cannot validate statically)
    }

    /**
     * Value injection calls the method with no arguments, so the method must
     * accept zero required parameters and its return type must be assignable
     * to the property's declared type.
     */
    private function validateValueReturnType(ReflectionProperty $property, ReflectionMethod $method): void
    {
        if ($method->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException(
                'Value injection requires a zero-argument method: ' . $method->getName() . ' for property ' . $property->getName() . '.',
            );
        }

        $returnType = $method->getReturnType();
        if ($returnType === null) {
            return; // untyped method — cannot validate statically
        }

        if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), ['void', 'never'], true)) {
            throw new InvalidArgumentException(
                'Cannot inject the void/never return of ' . $method->getName() . ' into property ' . $property->getName() . '.',
            );
        }

        $propertyType = $property->getType();
        if ($propertyType === null) {
            throw new InvalidArgumentException(
                'Value injection requires a typed property: ' . $property->getName() . '.',
            );
        }

        if ($returnType instanceof ReflectionNamedType) {
            $this->assertNamedTypeAssignable($returnType, $propertyType, $property, 'return type of ' . $method->getName());

            return;
        }

        if ($returnType instanceof ReflectionUnionType) {
            foreach ($returnType->getTypes() as $member) {
                if ($member instanceof ReflectionNamedType) {
                    $this->assertNamedTypeAssignable($member, $propertyType, $property, 'return type of ' . $method->getName());
                }
            }
        }
        // intersection and other shapes: allow (cannot validate statically)
    }

    private function assertNamedTypeAssignable(
        ReflectionNamedType $source,
        ReflectionType $target,
        ReflectionProperty $property,
        string $sourceLabel,
    ): void {
        if ($source->allowsNull() && !$target->allowsNull()) {
            throw new InvalidArgumentException(
                'Nullable ' . $sourceLabel . ' requires a nullable property: ' . $property->getName() . '.',
            );
        }

        if (!$this->isAssignable($source, $target)) {
            throw new InvalidArgumentException(
                'Type ' . $source->getName() . ' of ' . $sourceLabel . ' does not match property type ' . $property->getName() . '.',
            );
        }
    }

    private function isAssignable(ReflectionNamedType $source, ReflectionType $target): bool
    {
        if ($target instanceof ReflectionNamedType) {
            $targetName = $target->getName();
            if ($targetName === 'mixed') {
                return true;
            }
            if ($source->getName() === $targetName) {
                return true;
            }
            if ($targetName === 'object' && !$source->isBuiltin()) {
                return true;
            }
            if ($source->getName() === 'array' && $targetName === 'iterable') {
                return true;
            }
            if (!$source->isBuiltin() && !$target->isBuiltin()) {
                return is_a($source->getName(), $targetName, true);
            }

            return false;
        }

        if ($target instanceof ReflectionUnionType) {
            foreach ($target->getTypes() as $member) {
                if ($member instanceof ReflectionNamedType && $this->isAssignable($source, $member)) {
                    return true;
                }
            }

            return false;
        }

        if ($target instanceof ReflectionIntersectionType) {
            foreach ($target->getTypes() as $member) {
                if (!$this->isAssignable($source, $member)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
/**
 * @phpstan-import-type CompiledPlan from DependencyInjector
 * @phpstan-import-type CallableEntry from DependencyInjector
 */
class PlanCache
{
    /** @var array<string, CompiledPlan>|null */
    public private(set) ?array $compiledPlans = null;

    public function __construct(
        public private(set) string $cacheLocation,
    ) {}

    public function clearCachedPlans(): void
    {
        if (!empty($this->cacheLocation) && file_exists($this->cacheLocation)) {
            unlink($this->cacheLocation);
        }

        $this->compiledPlans = null;
    }

    public function buildCacheKey(AsChildClass $target): string
    {
        return $target->getParentClass() . '::' . $target->identifier;
    }

    /**
     * @return array<string, CompiledPlan>
     */
    public function loadPlans(): array
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
     * @param CompiledPlan $plan
     */
    public function isUsablePlan(?array $plan): bool
    {
        if (!is_array($plan) || !isset($plan['properties'])) {
            return false;
        }

        foreach ($plan['properties'] as $property => $entry) {
            if (!is_string($property) || (!is_string($entry) && !$this->isCallablePlanEntry($entry))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, CompiledPlan> $plans
     */
    public function writePlans(array $plans): void
    {
        $directory = dirname($this->cacheLocation);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create dependency injector cache directory: ' . $directory . '.');
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('Dependency injector cache directory is not writable: ' . $directory . '.');
        }

        $this->compiledPlans = $plans;

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

    /**
     * @param CallableEntry $entry
     */
    private function isCallablePlanEntry(array $entry): bool
    {
        return is_array($entry)
            && isset($entry['class'], $entry['member'], $entry['kind'], $entry['lazy'])
            && is_string($entry['class']) && $entry['class'] !== ''
            && is_string($entry['member']) && $entry['member'] !== ''
            && in_array($entry['kind'], ['method', 'property'], true)
            && is_bool($entry['lazy']);
    }
}
/**
 * @phpstan-import-type CompiledPlan from DependencyInjector
 * @phpstan-import-type CallableEntry from DependencyInjector
 */
class ScopeAccessFactory
{

    /** @var Closure(ContainerInterface $container, CallableEntry $entry): mixed|null */
    public private(set) ?Closure $callableResolver = null;
    /** @var array<string, Closure> */
    public private(set) array $closureFactories = [];
    /** @var array<string, Closure> */
    public private(set) array $setters = [];

    public function callableResolver(): Closure
    {
        return $this->callableResolver ??= $this->resolveCallable(...);
    }

    /**
     * @param string 
     * @param class-string $scopeClass
     * @throws RuntimeException
     * @return Closure(ContainerInterface $container, AsChildClass $target, CompiledPlan $properties, Closure(ContainerInterface, CompiledPlan}): mixed $resolveCallable): void
     */
    public function createSetter(string $setterKey, string $scopeClass): Closure
    {
        $setter = Closure::bind(
            static function (ContainerInterface $container, AsChildClass $target, array $properties, Closure $resolveCallable): void {
                foreach ($properties as $property => $entry) {
                    $target->{$property} = is_array($entry)
                        ? $resolveCallable($container, $entry)
                        : $container->get($entry);
                }
            },
            null,
            $scopeClass,
        );

        if (!$setter instanceof Closure) {
            throw new RuntimeException('Unable to bind the dependency injector to the anonymous child scope.');
        }

        return $this->setters[$setterKey] ??= $setter;
    }

    /**
     * @param class-string $class
     * @param CallableEntry['kind'] $kind
     */
    private function createCallableFactory(string $class, string $member, string $kind, bool $lazy): Closure
    {
        $factory = Closure::bind(
            match ($kind) {
                'property' => static fn(object $instance): mixed => $instance->{$member},
                'method' => $lazy
                    ? static fn(object $instance): Closure => $instance->{$member}(...)
                    : static fn(object $instance): mixed => $instance->{$member}(),
            },
            null,
            $class,
        );

        if (!$factory instanceof Closure) {
            throw new RuntimeException('Unable to bind callable factory for ' . $class . '::' . $member . '.');
        }

        return $factory;
    }
    /**
     * Resolve an array callable [class, member] into either the member's
     * value (property kind), the method's return value (lazy: false), or a
     * closure bound to the owning instance scope (lazy: true), so private
     * and protected members stay accessible.
     *
     * @param CallableEntry $entry
     */
    private function resolveCallable(ContainerInterface $container, array $entry): mixed
    {
        $instance = $container->get($entry['class']);
        $factoryKey = $entry['class'] . '::' . $entry['member'] . '#' . $entry['kind'] . ($entry['lazy'] ? '#lazy' : '#value');
        $factory = $this->closureFactories[$factoryKey] ??= $this->createCallableFactory($entry['class'], $entry['member'], $entry['kind'], $entry['lazy']);

        return $factory($instance);
    }
}
