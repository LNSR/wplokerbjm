<?php

namespace WPLokerBJM\Core;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Log\Logger;

class Container
{
    private static ?ContainerInterface $container = null;
    public static ?string $CACHE_DIR = null;
    public static ?string $CACHE_FILE = null;

    private static function initializeCachePaths(): void
    {
        if (self::$CACHE_DIR === null) {
            $loc = self::cacheLocation();
            self::$CACHE_DIR = $loc['cacheDir'];
            self::$CACHE_FILE = $loc['cacheFile'];
        }
    }

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
     * @return ContainerInterface The configured DI container
     * @throws \Exception If container creation fails
     */
    public static function getContainer(): ContainerInterface
    {
        if (self::$container === null) {
            self::initializeCachePaths();
            try {
                $builder = new ContainerBuilder();

                // Configure caching for performance
                self::setupCache($builder);

                // Enable autowiring and attributes for automatic dependency injection
                $builder->useAutowiring(true);
                $builder->useAttributes(false);

                // Add all service definitions
                self::setupDefinitions($builder);

                // Build the container
                self::$container = $builder->build();
            } catch (\Exception $e) {
                Logger::error('Container', 'Container::getContainer error: ' . $e->getMessage());
                throw $e; // Re-throw as container is critical for application functionality
            }
        }

        return self::$container;
    }

    private static function setupDefinitions(ContainerBuilder $builder): void
    {
        $builder->addDefinitions(array_merge(
            // Auto-scanned definitions from the server/ directory
            \WPLokerBJM\Core\Container\Definitions\AutoScanned::getDefinitions(),

            // Manually defined dependencies for core services
            \WPLokerBJM\Core\Container\Definitions\Core::getDefinitions(),

        ));
    }

    private static function setupCache(ContainerBuilder $builder): void
    {
        if (!is_dir(self::$CACHE_DIR)) {
            if (!mkdir(self::$CACHE_DIR, 0755, true)) {
                Logger::error('Container', "Failed to create cache directory: " . self::$CACHE_DIR);
            }
        }

        $isProduction = !SharedUtils::isDevelopment();

        $compiledFile = self::$CACHE_FILE;
        $objectKey = CacheKey::COMPILED_CONTAINER_HASH;

        if ($isProduction && is_file($compiledFile)) {
            $currentHash = @hash_file('sha1', $compiledFile);
            $storedHash = Cache::get($objectKey);
            if ($storedHash !== $currentHash) {
                array_map('unlink', glob(self::$CACHE_DIR . '/*'));
                Cache::set($objectKey, $currentHash, 0);
            }
        }

        if (!$isProduction && is_dir(self::$CACHE_DIR)) {
            array_map('unlink', glob(self::$CACHE_DIR . '/*'));
        }

        if ($isProduction && self::$CACHE_DIR && is_dir(self::$CACHE_DIR)) {
            $builder->enableCompilation(self::$CACHE_DIR);
            if (function_exists('apcu_enabled') && apcu_enabled()) {
                $builder->enableDefinitionCache('wplokerbjm_container_cache');
            }
            // Write proxies to disk for additional performance boost
            $builder->writeProxiesToFile(true, self::$CACHE_DIR . '/');
        }
    }
}
