<?php
namespace WPLokerBJM\Core\Plugins\ThirdParty;
use WPLokerBJM\Core\Plugins\PluginConfigInterface;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\WPLokerBJMContainer;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};
use DI\Attribute\Injectable;

/**
 * LiteSpeed custom hooks extend
 * @link https://docs.litespeedtech.com/lscache/lscwp/api/
 */
final class Litespeed implements PluginConfigInterface
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
    #[Action('litespeed_purged_all')]
    public function clearObjectCache(): void
    {
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
        do_action('wpgraphql_cache_purge_all');
        WPLokerBJMContainer::getContainer(true);
    }

    /**
     * Override LiteSpeed's mobile detection to use TinyWP Mobile Detect's enhanced wp_is_mobile().
     */
    #[Filter('litespeed_is_mobile')]
    public function customMobileDetect()
    {
        return wp_is_mobile();
    }

}

/**
 * LiteSpeed GraphQL Integration
 */
class LiteSpeedGraphQLIntegration implements PluginConfigInterface
{

    public static function isActive(): bool
    {
        return PluginList::LiteSpeed->isActive() && PluginList::WpGraphql->isActive();
    }

    /**
     * Call litespeed_purge when graphql_purge is called
     */
    #[Action('graphql_purge')]
    public $purgeCache = static function ($keys): void {
            do_action('litespeed_purge', $keys); };

    /**
     * Set GraphQL Queries returned via HTTP GET|OPTIONS requests to be cacheable
     */
    public function setCacheable(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS')
            return;
        if (is_user_logged_in()) {
            do_action('litespeed_control_force_cacheable');
            do_action('litespeed_control_set_ttl', 3600);
            return;
        }
        do_action('litespeed_control_force_cacheable');
        do_action('litespeed_control_set_ttl', 86400);
    }

    public function addTagResponses(array $headers = []): array
    {
        if (isset($headers['X-GraphQL-Keys'])) {
            do_action('litespeed_tag_add', explode(' ', $headers['X-GraphQL-Keys']));
            unset($headers['X-GraphQL-Keys']);
        }

        return $headers;
    }
}