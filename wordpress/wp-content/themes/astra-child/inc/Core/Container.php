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

            $cacheDir = get_stylesheet_directory() . '/cache';
            if (!is_dir($cacheDir)) {
                if (!mkdir($cacheDir, 0755, true)) {
                    error_log("Failed to create cache directory: $cacheDir");
                }
            }

            $isProduction = defined('WP_ENV') && WP_ENV === 'production';

            if (!$isProduction && is_dir($cacheDir)) {
                array_map('unlink', glob("$cacheDir/*"));
            }

            if ($isProduction && $cacheDir && is_dir($cacheDir)) {
                $builder->enableCompilation($cacheDir);
                if (function_exists('apcu_enabled') && apcu_enabled()) {
                    $builder->enableDefinitionCache();
                }
            }
            $builder->useAutowiring(true);
            $builder->useAttributes(true);

            $builder->addDefinitions(array_merge(
                // Auto-scanned definitions
                \AstraChild\Core\Definitions\AutoScanned::getDefinitions(),

                // Manually defined dependencies
                \AstraChild\Core\Definitions\Core::getDefinitions(),
                \AstraChild\Core\Definitions\Repositories::getDefinitions(),
                \AstraChild\Core\Definitions\Factories::getDefinitions(),
            ));

            self::$container = $builder->build();
        }

        return self::$container;
    }
}
