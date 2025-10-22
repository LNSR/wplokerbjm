<?php
namespace WPLokerBJM\Core\Hooks;
use WPLokerBJM\Core\{TransientCache, ObjectCache};

/**
 * LiteSpeed General Hooks
 */
class LiteSpeed
{
    /**
     * Deletes the compiled container cache file when LiteSpeed cache is purged.
     * Also clears APCu and OPCache caches.
     * * Useful when deploying new code to ensure no stale cached code is used.
     * @return void
     */
    public static function clearObjectCacheAndTransient(): void
    {
        // Delete the compiled container file
        $file = get_stylesheet_directory() . '/cache/CompiledContainer.php';
        if (file_exists($file)) {
            unlink($file);
        }

        // clear APCu cache
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        // clear OPCache
        if (function_exists('wp_opcache_invalidate') && function_exists('wp_opcache_invalidate_directory')) {
            wp_opcache_invalidate($file, true);
            wp_opcache_invalidate_directory(get_stylesheet_directory() . '/server');
        }

        // Clear all transients with the cache prefix using Cache class (now includes timeouts)
        TransientCache::deletePattern('%');
        // Flush object cache to clear cached transients
        ObjectCache::flush();

    }
}

/**
 * LiteSpeed Filters Focused Hooks
 */
class LiteSpeedFilters
{
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