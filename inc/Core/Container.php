<?php

namespace AstraChild\Core;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

class Container
{
    private static ?ContainerInterface $container = null;

    /**
     * Get the DI container instance.
     */
    public static function getContainer(): ContainerInterface
    {
        if (self::$container === null) {
            $builder = new ContainerBuilder();

            $cacheDir = __DIR__ . '/../../cache';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            // Enable caching only in production
            $isProduction = defined('WP_ENV') && WP_ENV === 'production';

            // In development, always clear the cache to avoid stale definitions
            if (!$isProduction && is_dir($cacheDir)) {
                // Remove all files in the cache directory
                array_map('unlink', glob("$cacheDir/*"));
            }

            if ($isProduction) {
                $builder->enableCompilation($cacheDir);
            }

            $builder->addDefinitions(array_merge(
                \AstraChild\Core\Definitions\Core::getDefinitions(),
                \AstraChild\Core\Definitions\Models::getDefinitions(),
                \AstraChild\Core\Definitions\Repositories::getDefinitions(),
                \AstraChild\Core\Definitions\Factories::getDefinitions(),
                \AstraChild\Core\Definitions\ViewModels::getDefinitions(),
                \AstraChild\Core\Definitions\Services::getDefinitions(),
                \AstraChild\Core\Definitions\Controllers::getDefinitions(),
                \AstraChild\Core\Definitions\Views::getDefinitions(),
            ));

            self::$container = $builder->build();
        }

        return self::$container;
    }
}
