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

            $isProduction = defined('WP_ENV') && WP_ENV === 'production';

            if (!$isProduction && is_dir($cacheDir)) {
                array_map('unlink', glob("$cacheDir/*"));
            }

            if ($isProduction) {
                $builder->enableCompilation($cacheDir);
                if (function_exists('apcu_enabled') && apcu_enabled()) {
                    $builder->enableDefinitionCache();
                }
            }
            $builder->useAutowiring(true);

            $builder->addDefinitions(array_merge(
                \AstraChild\Core\Definitions\Core::getDefinitions(),
                \AstraChild\Core\Definitions\Factories::getDefinitions(),
            ));

            self::$container = $builder->build();
        }

        return self::$container;
    }
}
