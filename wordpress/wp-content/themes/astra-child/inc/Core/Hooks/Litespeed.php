<?php
namespace AstraChild\Core\Hooks;
use AstraChild\Core\Cache;
use AstraChild\Core\ObjectCache;

class Litespeed
{
    /**
     * Deletes the compiled container cache file when LiteSpeed cache is purged.
     */
    public static function clearObjectCacheAndTransient(): void
    {
        // Delete the compiled container file
        $file = get_stylesheet_directory() . '/cache/CompiledContainer.php';
        if (file_exists($file)) {
            unlink($file);
        }

        // Clear all transients with the cache prefix using Cache class (now includes timeouts)
        Cache::deletePattern('%');
        // Flush object cache to clear cached transients
        ObjectCache::flush();

        error_log('Litespeed: Cleared compiled container and all transients with prefix: ' . Cache::TRANSIENT_PREFIX . '. Flushed object cache for cached transients.');

    }

    /**
     * Exclude specific JS files from LiteSpeed Cache JS optimization.
     */
    public static function lscJsExcludes($excludes)
    {
        $excludes[] = '/wp-content/themes/astra-child/assets/';
        $excludes[] = 'main-';
        return $excludes;
    }

    /**
     * Exclude specific CSS files from LiteSpeed Cache CSS optimization.
     */
    public static function lscCssExcludes($excludes)
    {
        $excludes[] = '/wp-content/themes/astra-child/assets/';
        $excludes[] = 'main-';
        return $excludes;
    }
}
