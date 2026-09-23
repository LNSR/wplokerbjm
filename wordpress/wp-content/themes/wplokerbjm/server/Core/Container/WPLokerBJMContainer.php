<?php

namespace WPLokerBJM\Core\Container;

use DI\ContainerBuilder;
use DI\Container;
use WPLokerBJM\Core\Container\Definitions\{Core, Factory};
use WPLokerBJM\Shared\Log\Logger;


class WPLokerBJMContainer
{
    private static ?Container $container = null;
    /** @var __CLASS__::class */
    public static ?object $CACHE_INFO = null;

    private static function initializeCachePaths(?string $cacheDir = null, ?string $cacheFile = null): void
    {
        self::$CACHE_INFO ??= new class($cacheDir, $cacheFile) {
            public function __construct(public private(set) ?string $cacheDir, public private(set) ?string $cacheFile)
            {
                $this->cacheDir ??= \sprintf("%s/cache", rtrim(get_stylesheet_directory(), '/'));
                $this->cacheFile ??= \sprintf("%s/CompiledContainer.php", $this->cacheDir);
            }
        };
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
    public static function getContainer(bool $rebuild = false): Container
    {
        if (self::$container !== null && $rebuild === false) {
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
     */
    private static function setupDefinitions(ContainerBuilder $builder): void
    {
        $builder->addDefinitions(
            // Last position will overwrite previous definitions
            \array_merge(
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
        $hasCache = file_exists(self::$CACHE_INFO->cacheFile);

        if ($hasCache && !$rebuild) {
            $builder->enableCompilation(self::$CACHE_INFO->cacheDir);
            return;
        }

        if (!is_dir(self::$CACHE_INFO->cacheDir) && !mkdir(self::$CACHE_INFO->cacheDir, 0755, true)) {
            Logger::error('Container', "Failed to create cache directory: " . self::$CACHE_INFO->cacheDir);
            Logger::flush();
            return;
        }

        if (!is_writable(self::$CACHE_INFO->cacheDir)) {
            Logger::warning('Container', "Compilation directory not writable, skipping compilation: " . self::$CACHE_INFO->cacheDir);
            Logger::flush();
            return;
        }

        try {
            self::setupDefinitions($builder);

            $builder->enableCompilation(self::$CACHE_INFO->cacheDir);
            $builder->writeProxiesToFile(true, self::$CACHE_INFO->cacheFile . '/');
        } catch (\Exception $e) {
            Logger::warning('Container', 'Failed to enable compilation: ' . $e->getMessage());
        }
    }
}
