<?php

namespace WPLokerBJM\Core\Container;

use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use WPLokerBJM\Core\Cache;
use WPLokerBJM\Core\Container;

/**
 * Scans directories for autowirable PHP classes and interface implementers.
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
    private const CACHE_KEY_PREFIX = 'autowire_scanner_';
    private const CACHE_TTL = 86400; // 1 day

    private string $baseDirectory;
    private string $namespace;

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
        return self::CACHE_KEY_PREFIX . md5($this->baseDirectory . $this->namespace . $this->getDirectoryHash() . $mtime);
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
                error_log('AutowireScanner::getDirectoryHash: Failed to get directory mtime for ' . $this->baseDirectory);
                return '';
            }
            return md5((string) $dirMtime);
        } catch (\Exception $e) {
            error_log('AutowireScanner::getDirectoryHash error: ' . $e->getMessage());
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
        $cacheKey = $this->getCacheKey();

        // Check APCu first (fast in-memory cache)
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Fallback to Redis-based object cache
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // No cache hit: Perform the actual scan
        $definitions = $this->performAutowirableScan();

        // Store result in cache (APCu primary, Redis fallback)
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_store($cacheKey, $definitions, self::CACHE_TTL);
        } else {
            Cache::set($cacheKey, $definitions, self::CACHE_TTL);
        }

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
            $className = $this->getClassNameFromFile($file);

            if ($className && $this->isAutowirable($className)) {
                // Use autowiring for this class
                $definitions[$className] = \DI\autowire($className)->lazy();
            }
        }

        return $definitions;
    }

    /**
     * Get fully qualified class names of autowirable classes implementing a specific interface.
     *
     * Scans for classes that implement the given interface and are autowirable (concrete,
     * non-static, etc.). Used by the DI container to inject services automatically.
     *
     * Caches results similarly to scanForAutowirableClasses for performance.
     *
     * @param string $interface Fully qualified interface name (e.g., HooksInterface::class)
     * @return string[] Array of fully qualified class names that implement the interface
     */
    public function getInterfaceImplementerClassNames(string $interface): array
    {
        $cacheKey = $this->getCacheKey() . '_interface_names_' . md5($interface);

        // Check APCu first
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Fallback to Redis cache
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Perform the scan
        $implementers = $this->performInterfaceImplementerScan($interface);

        // Cache the result
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_store($cacheKey, $implementers, self::CACHE_TTL);
        } else {
            Cache::set($cacheKey, $implementers, self::CACHE_TTL);
        }

        return $implementers;
    }

    /**
     * Perform the actual interface implementer scanning logic.
     * 
     * @param string $interface The fully qualified interface name
     * @return string[] Array of fully qualified class names
     */
    private function performInterfaceImplementerScan(string $interface): array
    {
        $implementers = [];
        $phpFiles = $this->findPhpFiles();

        foreach ($phpFiles as $file) {
            $className = $this->getClassNameFromFile($file);

            if ($className && $this->isAutowirable($className)) {
                // Check if class implements the interface
                if (is_subclass_of($className, $interface) || in_array($interface, class_implements($className))) {
                    $implementers[] = $className;
                }
            }
        }

        return $implementers;
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
     * Extract the fully qualified class name from a PHP file.
     * 
     * This method reads the file content and uses token parsing to extract the namespace
     * and class name, then combines them into a fully qualified class name.
     * 
     * @param string $filePath The absolute path to the PHP file
     * @return string|null The fully qualified class name, or null if not found
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $tokens = token_get_all(file_get_contents($filePath));

        if ($tokens === false) {
            return null; // File could not be read
        }

        $namespace = '';
        $className = '';
        $inNamespace = false;
        $inClass = false;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenValue = $token[1];

                if ($tokenType === T_NAMESPACE) {
                    $inNamespace = true;
                    $namespace = '';
                } elseif ($inNamespace && $tokenType === T_NAME_QUALIFIED) {
                    $namespace = $tokenValue;
                } elseif ($tokenType === T_CLASS) {
                    $inClass = true;
                } elseif ($inClass && $tokenType === T_STRING) {
                    $className = $tokenValue;
                    break;
                }
            } elseif ($token === ';') {
                $inNamespace = false;
            }
        }

        // Validate that both namespace and class name were found
        if (empty($className) || empty($namespace)) {
            return null;
        }

        return $namespace . '\\' . $className;
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
     * Debug method to get information about scanned classes.
     * 
     * This method scans all PHP files and returns detailed information about each class found,
     * including whether it's autowirable and the reason if it's not. Useful for debugging
     * and understanding what classes are being detected and why some are skipped.
     * 
     * @return array[] Array of result arrays, each containing:
     *                 - 'file': Absolute path to the PHP file
     *                 - 'class': Fully qualified class name
     *                 - 'autowirable': Boolean indicating if class can be autowired
     *                 - 'reason': String explaining why class is not autowirable (if applicable)
     */
    public function debugScanResults(): array
    {
        $phpFiles = $this->findPhpFiles();
        $results = [];

        foreach ($phpFiles as $file) {
            $className = $this->getClassNameFromFile($file);

            if ($className) {
                $isAutowirable = $this->isAutowirable($className);
                $reason = '';

                if (!$isAutowirable) {
                    $reason = $this->getSkipReason($className);
                }

                $results[] = [
                    'file' => $file,
                    'class' => $className,
                    'autowirable' => $isAutowirable,
                    'reason' => $reason,
                ];
            }
        }

        return $results;
    }

    /**
     * Get the reason why a class is not autowirable.
     * 
     * This method uses the same validation logic as isAutowirable() to ensure consistency
     * between the two methods.
     * 
     * @param string $className The fully qualified class name
     * @return string The reason why the class is not autowirable
     */
    private function getSkipReason(string $className): string
    {
        try {
            // Use the same basic checks as isAutowirable
            if (!$this->passesBasicChecks($className)) {
                if (!class_exists($className)) {
                    return 'Class does not exist';
                }
                if ($className === self::class) {
                    return 'AutowireScanner (excluded to avoid circular dependency)';
                }
                return 'Basic validation failed';
            }

            $reflection = new ReflectionClass($className);

            // Use the same validation methods as isAutowirable
            if (!$this->isConcreteClass($reflection)) {
                if ($reflection->isInterface()) {
                    return 'Interface';
                }
                if ($reflection->isAbstract()) {
                    return 'Abstract class';
                }
                if ($reflection->isTrait()) {
                    return 'Trait';
                }
                if ($reflection->isFinal()) {
                    return 'Final class (cannot be proxied)';
                }
                return 'Not a concrete class';
            }

            if ($this->isStaticOnlyClass($reflection)) {
                return 'Static-only class';
            }

            if (!$this->hasAccessibleConstructor($reflection)) {
                return 'Non-public constructor';
            }

            return 'Unknown reason';
        } catch (\Exception $e) {
            return 'Exception: ' . $e->getMessage();
        }
    }
}
