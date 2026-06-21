<?php

namespace WPLokerBJM\Core\Container\Support;

use ReflectionClass;
use WPLokerBJM\Core\Container\Support\Utilities\FileScannerTrait;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\Container;

/**
 * Scans directories for autowirable PHP classes.
 *
 * This scanner recursively searches a base directory for PHP files, extracts class names,
 * and determines which classes are suitable for dependency injection (autowiring). It excludes
 * interfaces, abstracts, static-only classes, and files in specified directories (e.g., vendor).
 *
 * Key features:
 * - Caches results using APCu (primary) and Redis (fallback) for performance.
 * - Provides debug methods to inspect scan results and reasons for exclusions.
 * - Uses FileScannerTrait for shared file-scanning utilities.
 *
 * Used by the DI container to automatically register services without manual definitions.
 *
 * @see \WPLokerBJM\Core\Container\Definitions\AutoScanned
 * @see Utilities\FileScannerTrait
 */
class AutowireScanner
{
    use FileScannerTrait;

    private ?array $cachedDefinitions = null;

    public function __construct(private string $baseDirectory, private string $namespace = 'WPLokerBJM')
    {
        $this->baseDirectory = rtrim($baseDirectory, '/');
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * Get the cache key for the scanner results.
     * Includes the CompiledContainer mtime and directory hash to invalidate when container or files change.
     */
    private function getCacheKey(): string
    {
        $cachePath = Container::$CACHE_DIR;
        $mtime = is_dir($cachePath) ? filemtime($cachePath) : 0;
        return CacheKey::AUTOWIRE_SCANNER_PREFIX . md5($this->baseDirectory . $this->namespace . $this->getDirectoryHash() . $mtime);
    }

    /**
     * Scan directories recursively and return DI definitions for autowirable classes.
     *
     * Finds all PHP files in the base directory (excluding specified folders), extracts
     * class names, and creates autowire definitions for suitable classes. Skips interfaces,
     * abstracts, static-only classes, and those with non-public constructors.
     *
     * Uses caching for performance in all environments. APCu is preferred over Redis for speed.
     *
     * @return array Associative array of class names to DI autowire definitions
     */
    public function scanForAutowirableClasses(): array
    {
        // Check in-memory cache first
        if ($this->cachedDefinitions !== null) {
            return $this->cachedDefinitions;
        }

        $cacheKey = $this->getCacheKey();

        // Check APCu first (fast in-memory cache)
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                $this->cachedDefinitions = $cached;
                return $cached;
            }
        }

        // Fallback to Redis-based object cache
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            $this->cachedDefinitions = $cached;
            return $cached;
        }

        // No cache hit: Perform the actual scan
        $definitions = $this->performAutowirableScan();

        // Store result in cache (APCu primary, Redis fallback)
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_store($cacheKey, $definitions, 86400); // Cache for 1 day
        } else {
            Cache::set($cacheKey, $definitions, 86400); // Cache for 1 day
        }

        $this->cachedDefinitions = $definitions;
        return $definitions;
    }

    /**
     * Perform the actual autowirable class scanning logic.
     *
     * @return array Array of autowire definitions
     */
    private function performAutowirableScan(): array
    {
        $definitions = [];
        $phpFiles = $this->findPhpFiles();

        foreach ($phpFiles as $file) {
            $classNames = $this->getClassNamesFromFile($file);

            foreach ($classNames as $className) {
                if ($this->isAutowirable($className)) {
                    $definitions[$className] = \DI\autowire($className)->lazy();
                }
            }
        }

        return $definitions;
    }

    /**
     * Check if a class is suitable for autowiring.
     *
     * Performs multiple validation checks to determine if a class can be autowired:
     * - Class must exist
     * - Must not be the scanner itself (circular dependency)
     * - Must be a concrete class (not interface, abstract, trait, or final)
     * - Must not be static-only
     * - Must have a public constructor (or no constructor)
     *
     * @param string $className The fully qualified class name
     * @return bool True if the class is autowirable
     */
    private function isAutowirable(string $className): bool
    {
        try {
            if (!$this->passesBasicChecks($className)) {
                return false;
            }

            $reflection = new ReflectionClass($className);

            if ($this->isAttributeClass($reflection)) {
                return false;
            }

            if (!$this->isConcreteClass($reflection)) {
                return false;
            }

            if ($this->isStaticOnlyClass($reflection)) {
                return false;
            }

            if (!$this->hasAccessibleConstructor($reflection)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Perform basic validation checks on the class.
     *
     * @param string $className The class name to check
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
     * Check if the reflection represents a concrete class.
     *
     * A concrete class is one that can be instantiated and proxied.
     * Excludes interfaces, abstract classes, traits, and final classes.
     *
     * @param ReflectionClass $reflection The class reflection
     * @return bool True if it's a concrete class
     */
    private function isConcreteClass(ReflectionClass $reflection): bool
    {
        return !$reflection->isInterface()
            && !$reflection->isAbstract()
            && !$reflection->isTrait()
            && !$reflection->isFinal();
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
            if (strpos($method->getName(), '__') === 0) {
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
}
