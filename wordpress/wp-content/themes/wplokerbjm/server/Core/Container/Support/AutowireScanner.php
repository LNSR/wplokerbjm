<?php

namespace WPLokerBJM\Core\Container\Support;

use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\Container;
use WPLokerBJM\Shared\Log\Logger;

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
 *
 * Used by the DI container to automatically register services without manual definitions.
 *
 * @see \WPLokerBJM\Core\Container\Definitions\AutoScanned
 * @see \WPLokerBJM\Core\Container\Definitions\Core
 */
class AutowireScanner
{
    private string $baseDirectory;
    private string $namespace;
    private ?array $cachedDefinitions = null;
    private ?array $cachedHookRegistrations = null;

    public function __construct(string $baseDirectory, string $namespace = 'WPLokerBJM')
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
     * Get a hash of the modification time of the base directory.
     * Used for cache invalidation when files change.
     */
    private function getDirectoryHash(): string
    {
        try {
            $dirMtime = filemtime($this->baseDirectory);
            if ($dirMtime === false) {
                Logger::warning('AutowireScanner', 'AutowireScanner::getDirectoryHash: Failed to get directory mtime for ' . $this->baseDirectory);
                return '';
            }
            return md5((string) $dirMtime);
        } catch (\Exception $e) {
            Logger::error('AutowireScanner', 'AutowireScanner::getDirectoryHash error: ' . $e->getMessage());
            return '';
        }
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
                    // Use autowiring for this class
                    $definitions[$className] = \DI\autowire($className)->lazy();
                }
            }
        }

        return $definitions;
    }

    /**
     * Find all PHP files in the directory recursively.
     * 
     * This method traverses the base directory recursively to find all .php files,
     * while excluding certain directories that typically don't contain autowirable classes.
     * 
     * @return string[] Array of absolute file paths to PHP files
     */
    private function findPhpFiles(): array
    {
        // Create recursive iterator to traverse all directories and files
        $directoryIterator = new RecursiveDirectoryIterator(
            $this->baseDirectory,
            RecursiveDirectoryIterator::SKIP_DOTS
        );

        $recursiveIterator = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        // Filter to only include .php files
        $phpFilesIterator = new RegexIterator($recursiveIterator, '/\.php$/');

        $phpFiles = [];
        $excludedDirectories = $this->getExcludedDirectories();

        // Process each PHP file found
        foreach ($phpFilesIterator as $file) {
            $filePath = $file->getPathname();

            // Check if file is in an excluded directory
            $isInExcludedDirectory = $this->isFileInExcludedDirectory($filePath, $excludedDirectories);

            if (!$isInExcludedDirectory) {
                $phpFiles[] = $filePath;
            }
        }

        return $phpFiles;
    }

    /**
     * Check if a file path is within any of the excluded directories.
     * 
     * @param string $filePath The absolute file path to check
     * @param string[] $excludedDirectories List of directory names to exclude
     * @return bool True if the file is in an excluded directory
     */
    private function isFileInExcludedDirectory(string $filePath, array $excludedDirectories): bool
    {
        foreach ($excludedDirectories as $excludedDir) {
            $excludedPathPattern = DIRECTORY_SEPARATOR . $excludedDir . DIRECTORY_SEPARATOR;
            if (strpos($filePath, $excludedPathPattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the fully qualified class names from a PHP file.
     * 
     * This method reads the file content and uses token parsing to extract all namespaces
     * and class names, then combines them into fully qualified class names.
     * 
     * @param string $filePath The absolute path to the PHP file
     * @return string[] Array of fully qualified class names, or empty array if none found
     */
    private function getClassNamesFromFile(string $filePath): array
    {
        $tokens = token_get_all(file_get_contents($filePath));

        if ($tokens === false) {
            return []; // File could not be read
        }

        $classes = [];
        $currentNamespace = '';
        $inNamespace = false;
        $inClass = false;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenValue = $token[1];

                if ($tokenType === T_NAMESPACE) {
                    $inNamespace = true;
                    $currentNamespace = '';
                } elseif ($inNamespace && ($tokenType === T_NAME_QUALIFIED || $tokenType === T_STRING)) {
                    $currentNamespace = $tokenValue;
                    $inNamespace = false; // Assume single namespace declaration
                } elseif ($tokenType === T_CLASS) {
                    $inClass = true;
                } elseif ($inClass && $tokenType === T_STRING) {
                    $className = $tokenValue;
                    if (!empty($currentNamespace)) {
                        $classes[] = $currentNamespace . '\\' . $className;
                    } else {
                        $classes[] = $className;
                    }
                    $inClass = false; // Reset for next class
                }
            } elseif ($token === ';') {
                $inNamespace = false;
            }
        }

        return $classes;
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
            // Basic existence and circular dependency checks
            if (!$this->passesBasicChecks($className)) {
                return false;
            }

            $reflection = new ReflectionClass($className);

            // Exclude attribute classes
            if ($this->isAttributeClass($reflection)) {
                return false;
            }

            // Type checks (interface, abstract, trait)
            if (!$this->isConcreteClass($reflection)) {
                return false;
            }

            // Static-only check
            if ($this->isStaticOnlyClass($reflection)) {
                return false;
            }

            // Constructor accessibility check
            if (!$this->hasAccessibleConstructor($reflection)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // If we can't reflect on the class, skip it
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
        // Class must exist
        if (!class_exists($className)) {
            return false;
        }

        // Skip the AutowireScanner itself to avoid circular dependency
        if ($className === self::class) {
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

        // No constructor is fine (PHP will provide a default)
        if ($constructor === null) {
            return true;
        }

        // Constructor must be public
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
            // Skip magic methods like __construct, __destruct, etc.
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

    /**
     * Get the list of directories that should be excluded from scanning.
     * 
     * These directories typically contain:
     * - vendor: Third-party dependencies (already autoloaded)
     * - cache: Generated/cache files (not source code)
     * - tests/test: Test files (not production code)
     * - .git: Version control files (not source code)
     * - node_modules: Frontend dependencies (not PHP source)
     * 
     * @return string[] Array of directory names to exclude
     */
    private function getExcludedDirectories(): array
    {
        return [
            'vendor',
            'cache',
            'tests',
            'test',
            '.git',
            'node_modules',
        ];
    }

    /**
     * Get hook registrations from attributes on methods of autowirable classes.
     *
     * Scans public, non-static methods for #[Action] or #[Filter] attributes
     * and returns an array of hook registration data. Static methods are
     * intentionally skipped — hooks are resolved through the DI container
     * and therefore must be instance methods.
     *
     * @return array Array of hook data: ['class' => FQCN, 'method' => methodName, 'type' => 'action'|'filter', 'hook' => hookName, 'priority' => int, 'accepted_args' => int]
     */
    public function getHookRegistrations(): array
    {
        // Check in-memory cache first
        if ($this->cachedHookRegistrations !== null) {
            return $this->cachedHookRegistrations;
        }

        $cacheKey = $this->getCacheKey() . '_hook_registrations';

        // Check APCu first
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                $this->cachedHookRegistrations = $cached;
                return $cached;
            }
        }

        // Fallback to Redis cache
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            $this->cachedHookRegistrations = $cached;
            return $cached;
        }

        // Perform the scan
        $registrations = $this->performHookRegistrationScan();

        // Cache the result
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_store($cacheKey, $registrations, 86400); // Cache for 1 day
        } else {
            Cache::set($cacheKey, $registrations, 86400);
        }

        $this->cachedHookRegistrations = $registrations;
        return $registrations;
    }

    /**
     * Perform the actual hook registration scanning logic.
     *
     * Only public, **non-static** methods are considered: hooks are resolved
     * through the DI container so the owning service must be instantiable.
     * Static hook methods are intentionally ignored.
     *
     * @return array Array of hook registration data
     */
    private function performHookRegistrationScan(): array
    {
        $registrations = [];
        $phpFiles = $this->findPhpFiles();

        foreach ($phpFiles as $file) {
            $classNames = $this->getClassNamesFromFile($file);

            foreach ($classNames as $className) {
                if (class_exists($className)) {
                    try {
                        $reflection = new ReflectionClass($className);
                        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                            // Skip static methods — hooks must be instance methods
                            // because the container resolves the owning service lazily.
                            if ($method->isStatic()) {
                                continue;
                            }
                            foreach ($method->getAttributes(\WPLokerBJM\Core\Container\Attributes\Action::class) as $attribute) {
                                $action = $attribute->newInstance();
                                $registrations[] = [
                                    'class' => $className,
                                    'method' => $method->getName(),
                                    'type' => 'action',
                                    'hook' => $action->hook,
                                    'priority' => $action->priority,
                                    'accepted_args' => $action->acceptedArgs,
                                ];
                            }
                            foreach ($method->getAttributes(\WPLokerBJM\Core\Container\Attributes\Filter::class) as $attribute) {
                                $filter = $attribute->newInstance();
                                $registrations[] = [
                                    'class' => $className,
                                    'method' => $method->getName(),
                                    'type' => 'filter',
                                    'hook' => $filter->hook,
                                    'priority' => $filter->priority,
                                    'accepted_args' => $filter->acceptedArgs,
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        Logger::error('AutowireScanner', 'Error scanning hooks for class ' . $className . ': ' . $e->getMessage());
                    }
                }
            }
        }

        return $registrations;
    }
}
