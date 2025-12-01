<?php

namespace WPLokerBJM\Core\Container;

use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use WPLokerBJM\Core\TransientCache;

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
     * Includes the CompiledContainer mtime to invalidate when container changes.
     */
    private function getCacheKey(): string
    {
        $compiledContainerPath = get_stylesheet_directory() . '/cache/CompiledContainer.php';
        $mtime = file_exists($compiledContainerPath) ? filemtime($compiledContainerPath) : 0;
        return self::CACHE_KEY_PREFIX . md5($this->baseDirectory . $this->namespace . $mtime);
    }

    /**
     * Scan directories recursively and return definitions for autowirable.
     * Skips interfaces, abstract classes, and static-only classes.
     */
    public function scanForAutowirableClasses(): array
    {
        $isProduction = defined('WP_ENV') && WP_ENV === 'production';
        
        if (!$isProduction) {
            // Skip caching in development for immediate feedback
            return $this->performAutowirableScan();
        }

        $cacheKey = $this->getCacheKey();

        // Try transient cache (redirected to Redis via LiteSpeed Cache)
        $cached = TransientCache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Perform the scan
        $definitions = $this->performAutowirableScan();

        // Cache the result
        TransientCache::set($cacheKey, $definitions, self::CACHE_TTL);

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
                $definitions[$className] = \DI\autowire($className);
            }
        }

        return $definitions;
    }

    /**
     * Get class names of autowirable classes that implement a specific interface.
     * 
     * @param string $interface The fully qualified interface name
     * @return string[] Array of fully qualified class names
     */
    public function getInterfaceImplementerClassNames(string $interface): array
    {
        $isProduction = defined('WP_ENV') && WP_ENV === 'production';
        
        if (!$isProduction) {
            // Skip caching in development for immediate feedback
            return $this->performInterfaceImplementerScan($interface);
        }

        $cacheKey = $this->getCacheKey() . '_interface_names_' . md5($interface);

        // Try transient cache (redirected to Redis via LiteSpeed Cache)
        $cached = TransientCache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Perform the scan
        $implementers = $this->performInterfaceImplementerScan($interface);

        // Cache the result
        TransientCache::set($cacheKey, $implementers, self::CACHE_TTL);

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
     * This method reads the file content and uses regex to extract the namespace
     * and class name, then combines them into a fully qualified class name.
     * 
     * @param string $filePath The absolute path to the PHP file
     * @return string|null The fully qualified class name, or null if not found
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $fileContent = file_get_contents($filePath);
        
        if ($fileContent === false) {
            return null; // File could not be read
        }

        // Extract namespace using named capture group for clarity
        $namespacePattern = '/namespace\s+(?<namespace>[^;]+);/';
        preg_match($namespacePattern, $fileContent, $namespaceMatches);
        $namespace = $namespaceMatches['namespace'] ?? '';

        // Extract class name (supports abstract classes which we'll filter later)
        $classPattern = '/(?:abstract\s+)?class\s+(?<className>[A-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/';
        preg_match($classPattern, $fileContent, $classMatches);
        $className = $classMatches['className'] ?? '';

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
     * - Must be a concrete class (not interface, abstract, or trait)
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
     * @param ReflectionClass $reflection The class reflection
     * @return bool True if it's a concrete class
     */
    private function isConcreteClass(ReflectionClass $reflection): bool
    {
        return !$reflection->isInterface() 
            && !$reflection->isAbstract() 
            && !$reflection->isTrait();
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
        $methods = $reflection->getMethods();
        $properties = $reflection->getProperties();

        // Check for non-static properties - if any exist, it's not static-only
        foreach ($properties as $property) {
            if (!$property->isStatic()) {
                return false;
            }
        }

        // Check for non-static methods (excluding magic methods)
        $hasInstanceMethod = false;
        foreach ($methods as $method) {
            // Skip magic methods like __construct, __destruct, etc.
            if (strpos($method->getName(), '__') === 0) {
                continue;
            }

            if (!$method->isStatic()) {
                $hasInstanceMethod = true;
                break;
            }
        }

        // If no instance methods and no non-static properties, consider it static-only
        return !$hasInstanceMethod;
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
            'node_modules'
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
                    'reason' => $reason
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
