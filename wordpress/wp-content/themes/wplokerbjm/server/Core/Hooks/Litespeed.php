<?php
namespace WPLokerBJM\Core\Hooks;
use WPLokerBJM\Core\{TransientCache, ObjectCache};

/**
 * LiteSpeed General Hooks
 */
class Litespeed
{
    /**
     * Deletes the compiled container cache file when LiteSpeed cache is purged.
     * Also clears APCu and OPCache caches.
     * * Useful when deploying new code to ensure no stale cached code is used.
     * @return void
     */
    public static function clearObjectCacheAndTransient(): void
    {
        // Clear APCu cache first
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        // Invalidate OPCache
        if (function_exists('wp_opcache_invalidate') && function_exists('wp_opcache_invalidate_directory')) {
            $file = get_stylesheet_directory() . '/cache/CompiledContainer.php';
            wp_opcache_invalidate($file, true);
            wp_opcache_invalidate_directory(get_stylesheet_directory() . '/server');
        }

        // Clear transients and object cache
        TransientCache::deletePattern('%');
        ObjectCache::flush();

        // Clear entire cache folder last
        $cacheDir = get_stylesheet_directory() . '/cache';
        if (is_dir($cacheDir)) {
            array_map('unlink', glob("$cacheDir/*"));
        }

    }
}

/**
 * LiteSpeed Filters Focused Hooks
 */
class LiteSpeedFilters
{
    const pattern = '/wp-content/themes/wplokerbjm/';
    /**
     * Exclude specific JS files from LiteSpeed Cache JS optimization.
     */
    public static function lscJsExcludes($excludes)
    {
        $excludes[] = self::pattern;
        return $excludes;
    }

    /**
     * Exclude specific CSS files from LiteSpeed Cache CSS optimization.
     */
    public static function lscCssExcludes($excludes)
    {
        $excludes[] = self::pattern;
        return $excludes;
    }
}