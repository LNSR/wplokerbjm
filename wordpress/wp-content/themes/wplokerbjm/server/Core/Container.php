<?php

namespace WPLokerBJM\Core;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

class Container
{
    private static ?ContainerInterface $container = null;

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
            try {
                $builder = new ContainerBuilder();

                // Configure caching for performance
                self::setupCache($builder);

                // Enable autowiring and attributes for automatic dependency injection
                $builder->useAutowiring(true);
                $builder->useAttributes(true);

                // Add all service definitions
                self::setupDefinitions($builder);

                // Build the container
                self::$container = $builder->build();
            } catch (\Exception $e) {
                error_log('Container::getContainer error: ' . $e->getMessage());
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
        $cacheDir = get_stylesheet_directory() . '/cache';
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                error_log("Failed to create cache directory: $cacheDir");
            }
        }

        $isProduction = defined('WP_ENV') && WP_ENV === 'production';

        $compiledFile = $cacheDir . '/CompiledContainer.php';
        $objectKey = 'compiled_container_hash';

        if ($isProduction && is_file($compiledFile)) {
            $currentHash = @hash_file('sha1', $compiledFile);
            $storedHash = \WPLokerBJM\Core\Cache::get($objectKey);
            if ($storedHash !== $currentHash) {
                array_map('unlink', glob("$cacheDir/*"));
                \WPLokerBJM\Core\Cache::set($objectKey, $currentHash, 0);
            }
        }

        if (!$isProduction && is_dir($cacheDir)) {
            array_map('unlink', glob("$cacheDir/*"));
        }

        if ($isProduction && $cacheDir && is_dir($cacheDir)) {
            $builder->enableCompilation($cacheDir);
            if (function_exists('apcu_enabled') && apcu_enabled()) {
                $builder->enableDefinitionCache('wplokerbjm_container_cache');
            }
            // Write proxies to disk for additional performance boost
            $builder->writeProxiesToFile(true, $cacheDir . '/');
        }
    }
}
