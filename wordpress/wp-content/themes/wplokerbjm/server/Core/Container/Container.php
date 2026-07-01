<?php

namespace WPLokerBJM\Core\Container;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Definitions\{AutoScanned, Core, Factory};
use WPLokerBJM\Shared\Log\Logger;

class Container
{
    private static ?ContainerInterface $container = null;
    public static ?string $CACHE_DIR = null;
    public static ?string $CACHE_FILE = null;

    private static function initializeCachePaths(): void
    {
        if (self::$CACHE_DIR !== null)
            return;

        $loc = self::cacheLocation();
        self::$CACHE_DIR = $loc['cacheDir'];
        self::$CACHE_FILE = $loc['cacheFile'];
    }

    /**
     * @return array{cacheDir: string, cacheFile: string}
     */
    private static function cacheLocation(): array
    {
        $cacheDir = get_stylesheet_directory() . '/cache';
        $cacheFile = $cacheDir . '/CompiledContainer.php';

        $cache = [
            'cacheDir' => $cacheDir,
            'cacheFile' => $cacheFile,
        ];

        return $cache;
    }

    /**
     * Get the DI container instance.
     * 
     * Creates and configures a PHP-DI container with autowiring, caching, and custom definitions.
     * Uses singleton pattern to ensure only one container instance exists.
     * 
     * @param bool|null $rebuild Whether to rebuild the container (skip cache). Default false.
     * @return ContainerInterface The configured DI container
     * @throws \Exception If container creation fails
     */
    public static function getContainer(?bool $rebuild = null): ContainerInterface
    {
        if (self::$container !== null && !$rebuild)
            return self::$container;

        self::initializeCachePaths();

        try {
            $builder = new ContainerBuilder();

            // Configure caching for performance
            self::setupCache($builder);

            // Enable autowiring and attributes for automatic dependency injection
            $builder->useAutowiring(true);
            $builder->useAttributes(false);

            // Add all service definitions
            if (!file_exists(self::$CACHE_FILE)) {
                self::setupDefinitions($builder);
            }

            // Build the container
            self::$container = $builder->build();
        } catch (\Exception $e) {
            Logger::error('Container', 'Container::getContainer error: ' . $e->getMessage());
            throw $e; // Re-throw as container is critical for application functionality
        }

        return self::$container;
    }


    private static function setupDefinitions(ContainerBuilder $builder): void
    {
        $builder->addDefinitions(array_merge(
            // Auto-scanned definitions from the server/ directory
            AutoScanned::getDefinitions(),

            // Interface bindings and factory definitions
            Factory::getDefinitions(),

            // Manually defined dependencies for core services
            Core::getDefinitions(),
        ));
    }

    private static function setupCache(ContainerBuilder $builder): void
    {
        if (!is_dir(self::$CACHE_DIR) && !mkdir(self::$CACHE_DIR, 0755, true)) {
            Logger::error('Container', "Failed to create cache directory: " . self::$CACHE_DIR);
        }

        if (!self::$CACHE_DIR || !is_dir(self::$CACHE_DIR)) {
            Logger::warning('Container', "Compilation directory not writable, skipping compilation: " . self::$CACHE_DIR);
            return;
        }

        if (!is_writable(self::$CACHE_DIR)) {
            Logger::warning('Container', "Compilation directory not writable, skipping compilation: " . self::$CACHE_DIR);
        } else {
            try {
                if (function_exists('apcu_enabled') && apcu_enabled()) {
                    $builder->enableDefinitionCache('wplokerbjm_container_cache');
                }
                $builder->enableCompilation(self::$CACHE_DIR);
                // Write proxies to disk for additional performance boost
                $builder->writeProxiesToFile(true, self::$CACHE_DIR . '/');
            } catch (\Throwable $e) {
                // Log and continue without compilation to keep tests/CI stable
                Logger::warning('Container', 'Failed to enable compilation: ' . $e->getMessage());
            }
        }
    }
}
