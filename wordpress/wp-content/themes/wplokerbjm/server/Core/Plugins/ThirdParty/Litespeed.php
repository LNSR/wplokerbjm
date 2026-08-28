<?php

namespace WPLokerBJM\Core\Plugins\ThirdParty;

use WPLokerBJM\Core\Plugins\PluginConfigInterface;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\WPLokerBJMContainer;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};
use WPLokerBJM\Bootstrap;
use WPLokerBJM\Shared\Log\Logger;

/**
 * LiteSpeed custom hooks extend
 * @link https://docs.litespeedtech.com/lscache/lscwp/api/
 */
class Litespeed implements PluginConfigInterface
{
    public static function isActive(): bool
    {
        return PluginList::LiteSpeed->isActive();
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

        do_action('wpgraphql_cache_purge_all');
        Cache::flushGroup(CacheKey::OBJECT_CACHE_PREFIX);
        // Clear entire cache folder
        $cacheDir = WPLokerBJMContainer::$CACHE_DIR;
        $deleteDirRecursive = static function (string $dir): bool {
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

                Logger::debug('Deleting item: ', $path);

                $file->isDir() && !$file->isLink() ? rmdir($path) : unlink($path);
            }

            return rmdir($dir);
        };
        $deleteDirRecursive($cacheDir);

        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        if (function_exists('wp_opcache_invalidate') && function_exists('wp_opcache_invalidate_directory')) {
            wp_opcache_invalidate_directory(get_stylesheet_directory());
        }

        WPLokerBJMContainer::getContainer(true);
    }

    /**
     * Override LiteSpeed's mobile detection to use TinyWP Mobile Detect's enhanced wp_is_mobile().
     */
    #[Filter('litespeed_is_mobile')]
    public function isMobile():bool
    {
        return wp_is_mobile();
    }
}
