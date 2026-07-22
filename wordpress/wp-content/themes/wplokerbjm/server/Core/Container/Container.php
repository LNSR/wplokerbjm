<?php

namespace WPLokerBJM\Core\Container;

use DI\ContainerBuilder;
use DI\Container;
use WPLokerBJM\Core\Container\Definitions\{Core, Factory};
use WPLokerBJM\Shared\Log\Logger;

class WPLokerBJMContainer
{
    private static ?Container $container = null;
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
     * @return Container The configured DI container
     * @throws \Exception If container creation fails
     */
    public static function getContainer(?bool $rebuild = null): Container
    {
        if (self::$container !== null && !$rebuild) {
            return self::$container;
        }

        self::initializeCachePaths();

        try {
            $builder = new ContainerBuilder();

            $builder->useAutowiring(true);
            $builder->useAttributes(true);

            self::setupCache($builder, (bool) $rebuild);

            self::$container = $builder->build();
        } catch (\Exception $e) {
            Logger::error('Container', 'Container::getContainer error: ' . $e->getMessage());
            Logger::flush();
            throw $e;
        }
        return self::$container;
    }


    /**
     * Setup definitions
     * @param ContainerBuilder $builder
     */
    private static function setupDefinitions(ContainerBuilder $builder): void
    {
        $builder->addDefinitions(
            // Last position will overwrite previous definitions
            array_merge(
                Core::getDefinitions(),
                // factory definitions
                Factory::getDefinitions(),
            )
        );
    }

    /**
     * Configures container compilation using strict guard clauses to minimize disk I/O.
     * @param ContainerBuilder $builder
     * @param bool $rebuild
     */
    private static function setupCache(ContainerBuilder $builder, bool $rebuild): void
    {
        $hasCache = file_exists(self::$CACHE_FILE);

        if ($hasCache && !$rebuild) {
            $builder->enableCompilation(self::$CACHE_DIR);
            return;
        }

        if (!is_dir(self::$CACHE_DIR) && !mkdir(self::$CACHE_DIR, 0755, true)) {
            Logger::error('Container', "Failed to create cache directory: " . self::$CACHE_DIR);
            Logger::flush();
            return;
        }

        if (!is_writable(self::$CACHE_DIR)) {
            Logger::warning('Container', "Compilation directory not writable, skipping compilation: " . self::$CACHE_DIR);
            Logger::flush();
            return;
        }

        try {
            self::setupDefinitions($builder);

            $builder->enableCompilation(self::$CACHE_DIR);
            $builder->writeProxiesToFile(true, self::$CACHE_DIR . '/');
        } catch (\Throwable $e) {
            Logger::warning('Container', 'Failed to enable compilation: ' . $e->getMessage());
            Logger::flush();
        }
    }
}
