<?php
namespace WPLokerBJM\Core\Hooks;
use WPLokerBJM\Core\Cache;
use WPLokerBJM\Core\ObjectCache;

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

    }

    /**
     * Exclude specific JS files from LiteSpeed Cache JS optimization.
     */
    public static function lscJsExcludes($excludes)
    {
        $excludes[] = '/wp-content/themes/wplokerbjm/assets/';
        $excludes[] = 'main-';
        return $excludes;
    }

    /**
     * Exclude specific CSS files from LiteSpeed Cache CSS optimization.
     */
    public static function lscCssExcludes($excludes)
    {
        $excludes[] = '/wp-content/themes/wplokerbjm/assets/';
        $excludes[] = '/wp-content/themes/wplokerbjm/style.css';
        $excludes[] = 'main-';
        return $excludes;
    }
}
