<?php
namespace WPLokerBJM\Core\Plugins;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\Container;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};


/**
 * LiteSpeed custom hooks extend
 * @link https://docs.litespeedtech.com/lscache/lscwp/api/
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
    public function clearObjectCache(): void
    {
        if (!SharedUtils::isPluginActive(PluginList::LiteSpeed)) {
            return;
        }
        // Clear APCu cache first
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        // Invalidate OPCache
        if (function_exists('wp_opcache_invalidate') && function_exists('wp_opcache_invalidate_directory')) {
            wp_opcache_invalidate_directory(get_stylesheet_directory());
        }

        Cache::flushGroup(CacheKey::OBJECT_CACHE_PREFIX);

        // Clear entire cache folder last
        $cacheDir = Container::$CACHE_DIR;
        if (is_dir($cacheDir)) {
            array_map('unlink', glob("$cacheDir/*"));
        }
        Container::getContainer(true);
    }

        /**
     * Override LiteSpeed's mobile detection to use TinyWP Mobile Detect's enhanced wp_is_mobile().
     */
    #[Filter('litespeed_is_mobile', 0)]
    public function customMobileDetect()
    {
        return wp_is_mobile();
    }

}

/**
 * LiteSpeed GraphQL Integration
 */
class LiteSpeedGraphQL
{

    public static function isActive(): bool
    {
        return SharedUtils::isPluginActive(PluginList::LiteSpeed);
    }

    /**
     * Set GraphQL Queries returned via HTTP GET requests to be cacheable
     */
    #[Action('graphql_process_http_request_response', 2)]
    public function setCacheable(): void
    {
        if ('GET' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        if (is_user_logged_in()) {
            do_action('litespeed_control_set_private');
        } else {
            do_action('litespeed_control_force_cacheable');
        }
    }

    /**
     * Add LiteSpeed tags, unset the x-graphql-keys
     */
    #[Filter('graphql_response_headers_to_send', 11)]
    public function tagResponses(array $headers = []): array
    {

        if (isset($headers['X-GraphQL-Keys'])) {
            $keys = $headers['X-GraphQL-Keys'];

            do_action('litespeed_tag_add', explode(' ', $keys));

            unset($headers['X-GraphQL-Keys']);
        }
        if (isset($headers['X-LiteSpeed-Cache-Control'])) {
            unset($headers['X-LiteSpeed-Cache-Control']);
            if (is_user_logged_in()) {
                header('X-LiteSpeed-Cache-Control: private, no-cache, must-revalidate');
            } else {
                header('X-LiteSpeed-Cache-Control: public, must-revalidate, max-age=60, stale-while-revalidate=3600, s-maxage=604800, stale-if-error=86400');
            }
        }
        return $headers;
    }

    /**
     * Call litespeed_purge when graphql_purge is called
     */
    #[Action('graphql_purge')]
    public function purgeCache($keys): void
    {
        do_action('litespeed_purge', $keys);
    }
}