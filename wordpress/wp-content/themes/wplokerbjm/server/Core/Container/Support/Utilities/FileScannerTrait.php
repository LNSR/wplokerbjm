<?php

namespace WPLokerBJM\Core\Container\Support\Utilities;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Shared file-scanning utilities for directory-based PHP class scanners.
 *
 * Provides methods to recursively find PHP files, extract class names from them,
 * filter excluded directories, and compute directory hashes for cache invalidation.
 *
 * Requires the using class to have a `$this->baseDirectory` property (typically
 * set via constructor injection) pointing to the directory to scan.
 */
trait FileScannerTrait
{
    /**
     * Find all PHP files in the directory recursively.
     *
     * Traverses the base directory recursively, excluding specified directories
     * (e.g., vendor, cache, tests) that typically don't contain scannable classes.
     *
     * @return string[] Array of absolute file paths to PHP files
     */
    private function findPhpFiles(): array
    {
        $directoryIterator = new RecursiveDirectoryIterator(
            $this->baseDirectory,
            RecursiveDirectoryIterator::SKIP_DOTS
        );

        $recursiveIterator = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $phpFilesIterator = new RegexIterator($recursiveIterator, '/\.php$/');

        $phpFiles = [];
        $excludedDirectories = $this->getExcludedDirectories();

        foreach ($phpFilesIterator as $file) {
            $filePath = $file->getPathname();

            if (!$this->isFileInExcludedDirectory($filePath, $excludedDirectories)) {
                $phpFiles[] = $filePath;
            }
        }

        return $phpFiles;
    }

    /**
     * Extract the fully qualified class names from a PHP file.
     *
     * Reads the file content and uses token parsing to extract all namespaces
     * and class names, combining them into fully qualified class names.
     *
     * @param string $filePath The absolute path to the PHP file
     * @return string[] Array of fully qualified class names, or empty array if none found
     */
    private function getClassNamesFromFile(string $filePath): array
    {
        $tokens = token_get_all(file_get_contents($filePath));

        if ($tokens === false) {
            return [];
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
                    $inNamespace = false;
                } elseif ($tokenType === T_CLASS) {
                    $inClass = true;
                } elseif ($inClass && $tokenType === T_STRING) {
                    $className = $tokenValue;
                    if (!empty($currentNamespace)) {
                        $classes[] = $currentNamespace . '\\' . $className;
                    } else {
                        $classes[] = $className;
                    }
                    $inClass = false;
                }
            } elseif ($token === ';') {
                $inNamespace = false;
            }
        }

        return $classes;
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
     * Get a hash of the modification time of the base directory.
     *
     * Used for cache invalidation when files change.
     *
     * @return string MD5 hash of directory mtime, or empty string on failure
     */
    private function getDirectoryHash(): string
    {
        try {
            $dirMtime = filemtime($this->baseDirectory);
            if ($dirMtime === false) {
                Logger::warning('FileScannerTrait', 'Failed to get directory mtime for ' . $this->baseDirectory);
                return '';
            }
            return md5((string) $dirMtime);
        } catch (\Exception $e) {
            Logger::error('FileScannerTrait', 'getDirectoryHash error: ' . $e->getMessage());
            return '';
        }
    }
}
