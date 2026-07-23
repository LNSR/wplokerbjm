<?php

namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery;
use ReflectionClass;
use DI\Attribute\Injectable;
use WPLokerBJM\Core\Container\Support\Utilities\FileScannerTrait;
use DI\Definition\AutowireDefinition;

/**
 * Scans directories for autowirable PHP classes.
 *
 * Recursively searches a base directory for PHP files, extracts class names,
 * and creates PHP-DI autowire definitions for suitable classes. Excludes
 * interfaces, abstracts, static-only classes, and attribute classes.
 *
 * @see \WPLokerBJM\Core\Container\Definitions\Core
 * @see Utilities\FileScannerTrait
 */
class AutowireScanner
{
    use FileScannerTrait;

    /** @var array<class-string, AutowireDefinition>|null */
    private ?array $cachedDefinitions = null;

    public function __construct(private string $baseDirectory, private string $namespace = 'WPLokerBJM')
    {
        $this->baseDirectory = rtrim($baseDirectory, '/');
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * Scan directories and return DI definitions for autowirable classes.
     *
     * Scans all PHP files, extracts class names, creates autowire definitions
     * for concrete, instantiable classes. Results are cached in-memory for
     * subsequent calls within the same request.
     *
     * @return array<class-string, AutowireDefinition> Class → autowire definition
     */
    public function scanForAutowirableClasses(): array
    {
        if ($this->cachedDefinitions !== null) {
            return $this->cachedDefinitions;
        }

        $definitions = $this->performAutowirableScan();
        $this->cachedDefinitions = $definitions;
        return $definitions;
    }

    /**
     * Perform the actual autowirable class scanning logic.
     *
     * @return array<class-string, AutowireDefinition>
     */
    private function performAutowirableScan(): array
    {
        $definitions = [];
        $phpFiles = $this->findPhpFiles();

        foreach ($phpFiles as $file) {
            $classNames = $this->getClassNamesFromFile($file);

            foreach ($classNames as $className) {
                $checkList = $this->isAutowirable($className);

                if (!$checkList['autowirable']) {
                    continue;
                }

                if ($checkList['lazy']) {
                    $definitions[$className] = \DI\autowire($className)->lazy();
                } else {
                    $definitions[$className] = \DI\autowire($className);
                }
            }
        }

        return $definitions;
    }

    /**
     * Check if a class is suitable for autowiring and determine if it should be lazy loaded.
     *
     * Performs multiple validation checks to determine if a class can be autowired.
     *
     * @param class-string $className name of class being inspected
     * @return list{autowirable: bool, lazy: bool}
     */
    private function isAutowirable(string $className): array
    {
        $checkList = [
            'autowirable' => false,
            'lazy' => false,
        ];
        try {
            if (!$this->passesBasicChecks($className)) {
                return $checkList;
            }

            $reflection = new ReflectionClass($className);

            if ($this->isAttributeClass($reflection)) {
                return $checkList;
            }

            if (!$this->isConcreteClass($reflection)) {
                return $checkList;
            }

            if ($this->isStaticOnlyClass($reflection)) {
                return $checkList;
            }

            if (!$this->hasAccessibleConstructor($reflection)) {
                return $checkList;
            }

            // Since it passed all concrete structural checks, inspect its lazy attribute status
            $checkList['autowirable'] = true;
            $checkList['lazy'] = $this->isAsLazyClass($reflection);
            return $checkList;
        } catch (\Exception $e) {
            return $checkList;
        }
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

        $excludeNamespace = $this->namespace . '\\Core\\Container\\Support\\';
        if (str_starts_with($className, $excludeNamespace)) {
            return false;
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
            && !$reflection->isEnum();
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
        return $attribute->isLazy();
    }
}