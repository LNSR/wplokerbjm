<?php
namespace WPLokerBJM\Core\Plugins;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\WPLokerBJMContainer;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};
use WPLokerBJM\Core\Plugins\WPGraphQL;
use DI\Attribute\Injectable;


trait LitespeedStatus
{
    public static function isActive(): bool
    {
        return PluginList::LiteSpeed->isActive();
    }
}

/**
 * LiteSpeed custom hooks extend
 * @link https://docs.litespeedtech.com/lscache/lscwp/api/
 */
#[Injectable(lazy: true)]
class Litespeed
{
    use LitespeedStatus;

    /**
     * Deletes the compiled container cache file when LiteSpeed cache is purged.
     * Also clears APCu and OPCache caches.
     * * Useful when deploying new code to ensure no stale cached code is used.
     * @return void
     */
    #[Action('litespeed_purged_all')]
    public function clearObjectCache(): void
    {
        if (!self::isActive()) {
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
        $cacheDir = WPLokerBJMContainer::$CACHE_DIR;
        if (is_dir($cacheDir)) {
            array_map('unlink', glob("$cacheDir/*"));
        }
        WPLokerBJMContainer::getContainer(true);
    }

    /**
     * Override LiteSpeed's mobile detection to use TinyWP Mobile Detect's enhanced wp_is_mobile().
     */
    #[Filter('litespeed_is_mobile', 5)]
    public function customMobileDetect()
    {
        if (!self::isActive()) return;
        return wp_is_mobile();
    }

}

/**
 * LiteSpeed GraphQL Integration
 */
#[Injectable(lazy: true)]
class LiteSpeedGraphQL
{
    use LitespeedStatus {
        isActive as isLitespeedActive;
    }

    public static function isActive(): bool {
        return self::isLitespeedActive() && WPGraphQL::isActive();
    }

    /**
     * Set GraphQL Queries returned via HTTP GET requests to be cacheable
     */
    #[Action('graphql_process_http_request_response', 2)]
    public function setCacheable(): void
    {
        if ('GET' !== $_SERVER['REQUEST_METHOD'] || !self::isActive()) {
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
        if (!self::isActive()) {
            return $headers;
        }
        if (isset($headers['X-GraphQL-Keys'])) {
            do_action('litespeed_tag_add', explode(' ', $headers['X-GraphQL-Keys']));
            unset($headers['X-GraphQL-Keys']);
        }

        if (isset($headers['X-LiteSpeed-Cache-Control'])) {
            if (is_user_logged_in()) {
                $headers['X-LiteSpeed-Cache-Control'] = 'private, no-cache';
            } else {
                $headers['X-LiteSpeed-Cache-Control'] = 'public, max-age=60, s-maxage=86400';
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
        if (!self::isActive()) return;
        do_action('litespeed_purge', $keys);
    }
}