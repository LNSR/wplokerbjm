<?php
namespace WPLokerBJM\Core\Plugins;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\Container;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\SharedUtils;

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
    #[Action('litespeed_purged_all')]
    public static function clearObjectCache(): void
    {
        if (!SharedUtils::isLitespeedActive()) {
            return;
        }
        Logger::info('LiteSpeed', 'Clearing object cache due to LiteSpeed cache purge');
        // Clear APCu cache first
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        // Invalidate OPCache
        if (function_exists('wp_opcache_invalidate') && function_exists('wp_opcache_invalidate_directory')) {
            $file = Container::$CACHE_FILE;
            wp_opcache_invalidate($file, true);
            wp_opcache_invalidate_directory(get_stylesheet_directory() . '/server');
        }

        Cache::flushGroup(CacheKey::OBJECT_CACHE_PREFIX);

        // Clear entire cache folder last
        $cacheDir = Container::$CACHE_DIR;
        if (is_dir($cacheDir)) {
            array_map('unlink', glob("$cacheDir/*"));
        }

    }

}

/**
 * LiteSpeed Filters Focused Hooks
 * @link https://docs.litespeedtech.com/lscache/lscwp/api/
 */
class LiteSpeedFilters
{
    private static function pattern(): string
    {
        if (defined('ABSPATH')) {
            return str_replace(ABSPATH, '/', get_stylesheet_directory() . '/assets/dist/');
        }
        return '/wp-content/themes/' . get_stylesheet() . '/assets/dist/';
    }
    
    /**
     * Exclude specific JS files from LiteSpeed Cache JS optimization.
     */
    #[Filter('litespeed_optimize_js_excludes', 0)]
    public static function lscJsExcludes($excludes)
    {
        if (!SharedUtils::isLitespeedActive()) {
            return $excludes;
        }
        $excludes[] = self::pattern();
        return $excludes;
    }

    /**
     * Exclude specific CSS files from LiteSpeed Cache CSS optimization.
     */
    #[Filter('litespeed_optimize_css_excludes', 0)]
    public static function lscCssExcludes($excludes)
    {
        if (!SharedUtils::isLitespeedActive()) {
            return $excludes;
        }
        $excludes[] = self::pattern();
        return $excludes;
    }

    /**
     * Override LiteSpeed's mobile detection to use TinyWP Mobile Detect's enhanced wp_is_mobile().
     */
    #[Filter('litespeed_is_mobile', 0)]
    public static function customMobileDetect()
    {
        return wp_is_mobile();
    }
}

/**
 * LiteSpeed GraphQL Integration
 */
class LiteSpeedGraphQL
{
    /**
     * Force GraphQL Queries returned via HTTP GET requests to be cacheable
     */
    #[Action('graphql_process_http_request_response', 0)]
    public static function forceCacheable(): void
    {
        if (!SharedUtils::isLitespeedActive()) {
            return;
        }
        if ('GET' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        do_action('litespeed_control_force_cacheable');
    }

    /**
     * Add LiteSpeed tags, unset the x-graphql-keys
     */
    #[Filter('graphql_response_headers_to_send', 10, 1)]
    public static function tagResponses(array $headers = []): array
    {
        if (!SharedUtils::isLitespeedActive()) {
            return $headers;
        }

        if (isset($headers['X-GraphQL-Keys'])) {
            do_action('litespeed_tag_add', explode(' ', $headers['X-GraphQL-Keys']));
            unset($headers['X-GraphQL-Keys']);
        }
        return $headers;
    }

    /**
     * Call litespeed_purge when graphql_purge is called
     */
    #[Action('graphql_purge', 0)]
    public static function purgeCache($keys): void
    {
        if (!SharedUtils::isLitespeedActive()) {
            return;
        }
        do_action('litespeed_purge', $keys);
    }
}