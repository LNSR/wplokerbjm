<?php

namespace WPLokerBJM\Core\Wordpress\Plugins\ThirdParty;

use Nette\Loaders\RobotLoader;
use WPLokerBJM\Core\Wordpress\Plugins\PluginConfigInterface;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\WPLokerBJMContainer;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Wordpress\Plugins\PluginList;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Bootstrap;
use WPLokerBJM\Core\Wordpress\ContainerRegistryEvent;
use WPLokerBJM\Shared\Log\Logger;

/**
 * LiteSpeed custom hooks extend
 * @link https://docs.litespeedtech.com/lscache/lscwp/api/
 */
final class Litespeed implements PluginConfigInterface
{
    public function __construct(private WPLokerBJMContainer $lokerBJMcontainer, private RobotLoader $robotLoader) {}

    public static function isActive(): bool
    {
        return PluginList::LiteSpeed->isActive();
    }

    #[Action('plugins_loaded', once: true)]
    public function boot(): void
    {
        \do_action(ContainerRegistryEvent::ACTIVATE_DEFERRED_BY_TAGS, ['litespeed']);
    }

    /**
     * Deletes the compiled container cache file when LiteSpeed cache is purged.
     * Also clears APCu and OPCache caches.
     * * Useful when deploying new code to ensure no stale cached code is used.
     * @return void
     */
    #[Action('litespeed_purged_all', once: true)]
    public function clearObjectCache(): void
    {
        Cache::flushGroup(CacheKey::OBJECT_CACHE_PREFIX);
        try {
            $this->deleteRucursive($this->lokerBJMcontainer->cacheDir);
        } catch (\Exception $e) {
            Logger::error('Error deleting cache folder: ', $e->getMessage());
        }

        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        if (function_exists('wp_opcache_invalidate') && function_exists('wp_opcache_invalidate_directory')) {
            wp_opcache_invalidate_directory(get_stylesheet_directory());
        }
        $this->robotLoader->rebuild();
        $this->lokerBJMcontainer
            ->initContainerBuilder(forceRebuild: true)
            ->buildContainer();
    }

    /**
     * Override LiteSpeed's mobile detection to use TinyWP Mobile Detect's enhanced wp_is_mobile().
     */
    #[Filter('litespeed_is_mobile')]
    public function isMobile(): bool
    {
        return wp_is_mobile();
    }

    private function deleteRucursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {

            $path = $file->getRealPath();
            if (str_contains($path, 'robotloader')) continue;

            Logger::debug('Deleting item: ', $path);

            $file->isDir() && !$file->isLink() ? rmdir($path) : unlink($path);
        }

        return rmdir($dir);
    }
}
