<?php

namespace WPLokerBJM\Core\Container\Support\Utilities;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
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
     * Uses nikic/php-parser to build a proper AST and walk it for class-like
     * declarations (classes, interfaces, traits, enums), combining them with
     * the file's namespace declaration into fully qualified class names.
     *
     * Handles all PHP 8+ constructs correctly — enum, readonly class, anonymous
     * classes (skipped), and multi-namespace files (each declaration is
     * associated with its containing namespace).
     *
     * @param string $filePath The absolute path to the PHP file
     * @return string[] Array of fully qualified class names, or empty array if none found
     */
    private function getClassNamesFromFile(string $filePath): array
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            return [];
        }

        try {
            $parser = (new ParserFactory())->createForHostVersion();
            $ast = $parser->parse($code);

            if ($ast === null) {
                return [];
            }

            $classes = [];
            $namespace = '';

            $traverser = new NodeTraverser();
            $traverser->addVisitor(new class($classes, $namespace) extends NodeVisitorAbstract {
                public function __construct(
                    private array &$classes,
                    private string &$namespace,
                ) {}

                public function enterNode(Node $node): void
                {
                    if ($node instanceof Node\Stmt\Namespace_) {
                        $this->namespace = $node->name?->toString() ?? '';
                    }

                    if ($node instanceof Node\Stmt\ClassLike && $node->name !== null) {
                        $className = $node->name->toString();
                        $this->classes[] = $this->namespace !== ''
                            ? $this->namespace . '\\' . $className
                            : $className;
                    }
                }
            });

            $traverser->traverse($ast);
            return $classes;
        } catch (\Throwable) {
            return [];
        }
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
}
