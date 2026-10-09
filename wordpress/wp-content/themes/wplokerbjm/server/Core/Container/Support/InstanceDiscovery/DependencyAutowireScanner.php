<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery;

use ReflectionClass;
use ScannerDefinition;
use WPLokerBJM\Core\Container\Attributes\Injectable;
use DI\Definition\AutowireDefinition;
use Nette\Loaders\RobotLoader;

/**
 * Scans directories for autowirable PHP classes.
 *
 * Recursively searches a base directory for PHP files, extracts class names,
 * and creates PHP-DI autowire definitions for suitable classes. Excludes
 * interfaces, abstracts, static-only classes, and attribute classes.
 *
 * @template TClass of class-string
 * @phpstan-type ScannerDefinition array{TClass, AutowireDefinition|null}
 * @see \WPLokerBJM\Core\Container\Definitions\Core
 * @see \WPLokerBJM\Bootstrap
 */
class DependencyAutowireScanner
{

    /** @var ScannerDefinition */
    private ?array $cachedDefinitions = null;
    private AutowireClassesChecker $checkClass;

    public function __construct(
        private RobotLoader $robotLoader,
        array $excludedSubNamespaces = [],
    ) {
        $formatExcludedNamespaces = array_map(static fn(string $namespace): string => trim($namespace, '\\'), $excludedSubNamespaces);
        $this->checkClass = new AutowireClassesChecker($formatExcludedNamespaces);
    }

    /**
     * Scan directories and return DI definitions for autowirable classes.
     *
     * Scans all PHP files, extracts class names, creates autowire definitions
     * for concrete, instantiable classes. Results are cached in-memory for
     * subsequent calls within the same request.
     *
     * @return ScannerDefinition Class → autowire definition
     */
    public function scanForAutowirableClasses(): array
    {
        return $this->cachedDefinitions ??= $this->performAutowirableScan();
    }

    /**
     * Perform the actual autowirable class scanning logic.
     *
     * @return ScannerDefinition
     */
    private function performAutowirableScan(): array
    {
        $definitions = [];

        foreach ($this->robotLoader->getIndexedClasses() as $className => $file) {
            $checkList = $this->checkClass->isAutowirable($className);

            if (!$checkList->isAutowirable) {
                continue;
            }

            $definitions[$className] = $checkList->isLazy ? \DI\autowire($className)->lazy() : \DI\autowire($className);
        }

        return $definitions;
    }
}

/**
 * @internal
 */
class AutowireClassesChecker
{
    public function __construct(private readonly array $excludedSubNamespaces) {}
    /**
     * Check if a class is suitable for autowiring and determine if it should be lazy loaded.
     *
     * Performs multiple validation checks to determine if a class can be autowired.
     * @param class-string $className name of class being inspected
     */
    public function isAutowirable(string $className)
    {
        $checkList = [];
        try {
            if (!$this->passesBasicChecks($className)) {
                return $this->createDTO($checkList);
            }

            $reflection = new ReflectionClass($className);

            if ($this->isAttributeClass($reflection)) {
                return $this->createDTO($checkList);
            }

            if (!$this->isConcreteClass($reflection)) {
                return $this->createDTO($checkList);
            }

            if ($this->isStaticOnlyClass($reflection)) {
                return $this->createDTO($checkList);
            }

            if (!$this->hasAccessibleConstructor($reflection)) {
                return $this->createDTO($checkList);
            }

            if ($this->hasNonAutowirableConstructor($reflection)) {
                return $this->createDTO($checkList);
            }

            if ($this->AttributeHasSkipTrue($reflection)) {
                return $this->createDTO($checkList);
            }
            $checkList['isLazy'] = $this->isAsLazyClass($reflection);
            $checkList['isAutowirable'] = true;
            return $this->createDTO($checkList);
        } catch (\Exception $e) {
            return $this->createDTO($checkList);
        }
    }

    /**
     * Create a DTO for autowirable class checking.
     * @param array{isAutowirable:bool, isLazy:bool} $options
     */
    private function createDTO(array $options = [])
    {
        $options['isAutowirable'] ??= false;
        $options['isLazy'] ??= false;
        return new readonly class (...$options) {
            public function __construct(public bool $isAutowirable = false, public bool $isLazy = false) {}
        };
    }

    /**
     * Perform basic validation checks on the class.
     *
     * @param class-string $className The class name to check
     * @return bool True if basic checks pass
     */
    private function passesBasicChecks(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        foreach ($this->excludedSubNamespaces as $excludedSubNamespace) {
            if (str_starts_with($className, $excludedSubNamespace)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the reflection represents a concrete, instantiable class.
     *
     * Excludes interfaces, abstract classes, and traits.
     * Final classes are included — PHP-DI's ProxyManager (v2.14+) handles
     * them via UninitializedLazyLoadingValueHolder on PHP 8.5+.
     *
     * @param ReflectionClass $reflection The class reflection
     * @return bool True if it's a concrete class
     */
    private function isConcreteClass(ReflectionClass $reflection): bool
    {
        return !$reflection->isInterface()
            && !$reflection->isAbstract()
            && !$reflection->isTrait()
            && !$reflection->isEnum()
            && !$reflection->isReadOnly();
    }

    /**
     * Check if the class has an accessible constructor.
     *
     * @param ReflectionClass $reflection The class reflection
     * @return bool True if constructor is accessible (public or none)
     */
    private function hasAccessibleConstructor(ReflectionClass $reflection): bool
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return true;
        }

        return $constructor->isPublic();
    }

    /**
     * Check if the class has a constructor that cannot be autowired by PHP-DI.
     *
     * Excludes classes with no constructor, or whose constructor parameters are
     * all either optional or resolvable types (classes, arrays, callbacks, etc.).
     * Only flags classes with required primitive-type arguments.
     *
     * @param ReflectionClass $reflection The class reflection
     * @return bool True if constructor cannot be autowired
     */
    private function hasNonAutowirableConstructor(ReflectionClass $reflection): bool
    {
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return false;
        }

        foreach ($constructor->getParameters() as $param) {
            // PHP can supply the default.
            if ($param->isOptional()) {
                continue;
            }

            // Required untyped parameter cannot be autowired safely.
            if (!$param->hasType()) {
                return true;
            }

            $type = $param->getType();

            // Required builtin cannot be resolved automatically.
            if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a class contains only static methods and no instance methods or properties.
     *
     * A static-only class is one that:
     * - Has no non-static (instance) properties
     * - Has no non-static (instance) methods (excluding magic methods like __construct)
     *
     * Such classes are typically utility classes and cannot be autowired as they don't
     * have instance state or behavior.
     *
     * @param ReflectionClass $reflection The class reflection to analyze
     * @return bool True if the class is static-only
     */
    private function isStaticOnlyClass(ReflectionClass $reflection): bool
    {
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            if (!$property->isStatic()) {
                return false;
            }
        }

        $methods = $reflection->getMethods();
        foreach ($methods as $method) {
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }
            if (!$method->isStatic()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the class is an attribute class.
     *
     * Attribute classes are marked with the #[Attribute] attribute and are not meant to be autowired.
     *
     * @param ReflectionClass $reflection The class reflection
     * @return bool True if the class is an attribute class
     */
    private function isAttributeClass(ReflectionClass $reflection): bool
    {
        return !empty($reflection->getAttributes(\Attribute::class));
    }

    /**
     * Check if a class has PHP-DI #[Injectable] attribute with lazy = true.
     *
     * @param ReflectionClass $reflection
     * @return bool
     */
    private function isAsLazyClass(ReflectionClass $reflection): bool
    {
        /** @var \ReflectionAttribute[] $attributes */
        $attributes = $reflection->getAttributes(Injectable::class);
        if (empty($attributes)) {
            return false;
        }

        /** @var Injectable $attribute */
        $attribute = $attributes[0]->newInstance();
        return $attribute->lazy;
    }
    /**
     * Check if a class has PHP-DI #[Injectable] attribute with skip = true.
     *
     * @param ReflectionClass $reflection
     * @return bool
     */
    private function AttributeHasSkipTrue(ReflectionClass $reflection): bool
    {
        /** @var \ReflectionAttribute[] $attributes */
        $attributes = $reflection->getAttributes(Injectable::class);
        if (empty($attributes)) {
            return false;
        }

        /** @var Injectable $attribute */
        $attribute = $attributes[0]->newInstance();
        return $attribute->skip;
    }
}
