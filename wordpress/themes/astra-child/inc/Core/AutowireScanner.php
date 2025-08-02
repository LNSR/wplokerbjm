<?php

namespace AstraChild\Core;

use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class AutowireScanner
{
    private string $baseDirectory;
    private string $namespace;

    public function __construct(string $baseDirectory, string $namespace = 'AstraChild')
    {
        $this->baseDirectory = rtrim($baseDirectory, '/');
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * Scan directories recursively and return definitions for autowiring.
     * Skips interfaces, abstract classes, and static-only classes.
     */
    public function scanForAutowirableClasses(): array
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
     * Find all PHP files in the directory recursively.
     */
    private function findPhpFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->baseDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $phpFiles = new RegexIterator($iterator, '/\.php$/');
        $files = [];
        $excludedDirs = $this->getExcludedDirectories();

        foreach ($phpFiles as $file) {
            $filePath = $file->getPathname();
            
            // Skip files in excluded directories
            $shouldSkip = false;
            foreach ($excludedDirs as $excludedDir) {
                if (strpos($filePath, DIRECTORY_SEPARATOR . $excludedDir . DIRECTORY_SEPARATOR) !== false) {
                    $shouldSkip = true;
                    break;
                }
            }
            
            if (!$shouldSkip) {
                $files[] = $filePath;
            }
        }

        return $files;
    }

    /**
     * Extract the fully qualified class name from a PHP file.
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        
        // Extract namespace
        $namespacePattern = '/namespace\s+([^;]+);/';
        preg_match($namespacePattern, $content, $namespaceMatches);
        $namespace = $namespaceMatches[1] ?? '';

        // Extract class name (including abstract classes but we'll filter them later)
        $classPattern = '/(?:abstract\s+)?class\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/';
        preg_match($classPattern, $content, $classMatches);
        
        if (!empty($classMatches[1]) && !empty($namespace)) {
            return $namespace . '\\' . $classMatches[1];
        }

        return null;
    }

    /**
     * Check if a class is suitable for autowiring.
     * Skips interfaces, abstract classes, and static-only classes.
     */
    private function isAutowirable(string $className): bool
    {
        try {
            // Skip if class doesn't exist
            if (!class_exists($className)) {
                return false;
            }

            // Skip the AutowireScanner itself to avoid circular dependency
            if ($className === self::class) {
                return false;
            }

            $reflection = new ReflectionClass($className);

            // Skip interfaces
            if ($reflection->isInterface()) {
                return false;
            }

            // Skip abstract classes
            if ($reflection->isAbstract()) {
                return false;
            }

            // Skip traits
            if ($reflection->isTrait()) {
                return false;
            }

            // Skip classes that only have static methods (no instance methods or properties)
            if ($this->isStaticOnlyClass($reflection)) {
                return false;
            }

            // Skip classes without a public constructor or with a private/protected constructor
            $constructor = $reflection->getConstructor();
            if ($constructor && !$constructor->isPublic()) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // If we can't reflect on the class, skip it
            return false;
        }
    }

    /**
     * Check if a class contains only static methods and no instance methods or properties.
     */
    private function isStaticOnlyClass(ReflectionClass $reflection): bool
    {
        $methods = $reflection->getMethods();
        $properties = $reflection->getProperties();

        // If there are non-static properties, it's not static-only
        foreach ($properties as $property) {
            if (!$property->isStatic()) {
                return false;
            }
        }

        // Check if all methods are static (excluding magic methods like __construct)
        $hasInstanceMethod = false;
        foreach ($methods as $method) {
            // Skip magic methods
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
     * Get excluded directories that should not be scanned.
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
     * Returns an array with details about each class found.
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
     */
    private function getSkipReason(string $className): string
    {
        try {
            if (!class_exists($className)) {
                return 'Class does not exist';
            }

            // Skip the AutowireScanner itself to avoid circular dependency
            if ($className === self::class) {
                return 'AutowireScanner (excluded to avoid circular dependency)';
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isInterface()) {
                return 'Interface';
            }

            if ($reflection->isAbstract()) {
                return 'Abstract class';
            }

            if ($reflection->isTrait()) {
                return 'Trait';
            }

            if ($this->isStaticOnlyClass($reflection)) {
                return 'Static-only class';
            }

            $constructor = $reflection->getConstructor();
            if ($constructor && !$constructor->isPublic()) {
                return 'Non-public constructor';
            }

            return 'Unknown reason';
        } catch (\Exception $e) {
            return 'Exception: ' . $e->getMessage();
        }
    }
}
