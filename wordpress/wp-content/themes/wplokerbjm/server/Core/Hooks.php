<?php

namespace WPLokerBJM\Core;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Hooks\Theme\{Enqueue, ThemeInject, DebloatWPTheme};
use WPLokerBJM\Core\Hooks\Plugins\{LiteSpeedFilters, Litespeed};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Services\Utilities\SSG\BotDetection;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Services\REST\RESTRoute;
use WPLokerBJM\Services\SSG\{PostsCRUDListener, RedirectToSSG};
use WPLokerBJM\Shared\Log\Logger;

/**
 * Global hooks registration for actions and filters.
 * Registers WordPress actions and filters to modify theme behavior.
 * * Might dump some temporary hooks here before promoting to dedicated classes/layer.
 */
class Hooks implements HooksInterface
{
    public function __construct(
        private BotDetection $botDetection,
        private PostsCRUDListener $postsCRUDListener,
        private RedirectToSSG $redirectToSSG,
        private RESTRoute $restRoute
    ) {
    }

    /*======================================================================
     | REGISTER HOOKS
     ======================================================================*/

    public function registerActions(): void
    {
        add_action('after_setup_theme', fn() => ThemeInject::addThemeSupport());
        add_action('wp_head', fn() => ThemeInject::injectThemeScript());
        add_action('wp_head', fn() => Enqueue::outputPreloadLinks());
        add_action('wp_head', fn() => ThemeInject::preloadLogo());
        add_action('wp_enqueue_scripts', fn() => DebloatWPTheme::removeWPLibrary(), 4);
        add_action('litespeed_purged_all', fn() => Litespeed::clearObjectCache());
        add_action('wp_enqueue_scripts', fn() => Enqueue::enqueueAssets());
        add_action('template_redirect', fn() => $this->oldPost410Redirect(), 4);
        add_action('template_redirect', fn() => self::redirectToHome(), 4);
        add_action('template_redirect', fn() => self::modifyLinkHeaders(), 8);
        add_action('send_headers', fn() => self::restHeaders());

        // API registerActions
        add_action('rest_api_init', fn() => $this->restRoute->registerRoutes());

        // Cache purging hooks
        add_action('save_post', fn(...$args) => $this->purgeQueryCaches(...$args));
        add_action('delete_post', fn(...$args) => $this->purgeQueryCaches(...$args));
        add_action('trashed_post', fn(...$args) => $this->purgeQueryCaches(...$args));
        add_action('delete_attachment', fn(...$args) => $this->purgeQueryCaches(...$args));
        add_action('created_term', fn(...$args) => $this->purgeQueryCaches(...$args));
        add_action('edited_term', fn(...$args) => $this->purgeQueryCaches(...$args));
        add_action('delete_term', fn(...$args) => $this->purgeQueryCaches(...$args));

        // Job-specific cache invalidation hooks
        add_action('save_post', fn(...$args) => $this->clearJobDataCache(...$args), 10, 2);
        add_action('delete_post', fn(...$args) => $this->clearJobDataCache(...$args));
        add_action('updated_post_meta', fn(...$args) => $this->clearJobDataCacheOnMeta(...$args), 10, 4);
        add_action('set_object_terms', fn(...$args) => $this->clearJobDataCacheOnTax(...$args), 10, 6);
        add_action('transition_post_status', fn(...$args) => $this->clearJobDataCacheOnStatusChange(...$args), 10, 3);

        // SSG hooks
        add_action('save_post', fn(...$args) => $this->postsCRUDListener->onSavePost(...$args), 10, 3);
        add_action('before_delete_post', fn(...$args) => $this->postsCRUDListener->onBeforeDeletePost(...$args), 1, 1);
        add_action('wp_trash_post', fn(...$args) => $this->postsCRUDListener->onTrashPost(...$args), 1, 1);
        add_action('template_redirect', fn() => $this->redirectToSSG->serveSSG(), 0);
        add_action('send_headers', fn() => $this->redirectToSSG->buildHeaders());
        add_action('wp_footer', fn() => $this->redirectToSSG->setCookieToHuman());
    }

    /*======================================================================
     | REGISTER FILTERS
     ======================================================================*/

    public function registerFilters(): void
    {
        add_filter('wp_robots', fn(...$args) => self::robotsMeta(...$args));
        add_filter('rest_pre_serve_request', fn(...$args) => self::filterRestHeaders(...$args), 10, 4);
        add_filter('litespeed_optimize_js_excludes', fn(...$args) => LiteSpeedFilters::lscJsExcludes(...$args), 0);
        add_filter('litespeed_optimize_css_excludes', fn(...$args) => LiteSpeedFilters::lscCssExcludes(...$args), 0);
        add_filter('option_active_plugins', fn(...$args) => $this->disablePluginsForDev(...$args), 0);
        add_filter('option_active_plugins', fn(...$args) => $this->disablePluginsforSimulatedProd(...$args), 0);
        add_filter('posts_search', fn(...$args) => $this->jobPostsSearchFilter(...$args), 10, 2);
        add_filter('site_icon_meta_tags', fn(...$args) => ThemeInject::addSiteIconMetaTags(...$args));
        add_filter('site_icon_image_sizes', fn(...$args) => [32, 48, 96, 144, 192, 256, 384, 512]);
    }

    /*======================================================================
     | REDIRECTS
     ======================================================================*/

    /**
     * Return 410 Gone for old job posts that have been trashed due to age.
     * * For deleted job posts (404 on single lowongan), return 410 Gone.
     * ! Notify search engines with 410 Gone for removed job posts.
     */
    public function oldPost410Redirect(): void
    {
        if (
            !is_404() ||
            is_preview() ||
            is_admin() ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            wp_doing_ajax() ||
            wp_doing_cron()
        ) {
            return;
        }

        $handleRemovedJob = function () {
            if ($this->botDetection->isBot()) {
                status_header(410);
                wp_die('This job posting has been removed.', 'Gone', ['response' => 410]);
            } else {
                wp_safe_redirect(home_url('/'), 302);
                exit;
            }
        };

        if (is_singular('lowongan') || strpos($_SERVER['REQUEST_URI'] ?? '', '/lowongan/') !== false) {
            $handleRemovedJob();
        } else {
            // Other 404s redirect to home
            wp_safe_redirect(home_url('/'), 302);
            exit;
        }
    }

    /**
     * Redirect to home if accessing the lowongan post type archive.
     */
    public static function redirectToHome(): void
    {
        // Avoid redirecting during admin, AJAX, REST API, cron, or preview requests
        if (
            (defined('REST_REQUEST') && REST_REQUEST) ||
            wp_doing_ajax() ||
            wp_doing_cron() ||
            is_preview()
        ) {
            return;
        }

        if (is_post_type_archive('lowongan')) {
            // Use wp_safe_redirect to ensure the redirect target is allowed
            wp_safe_redirect(home_url('/'), 302);
            exit;
        }
    }

    /*======================================================================
     | META TAGS
     ======================================================================*/

    /**
     * Adds noindex and nofollow directives to robots meta tag.
     * - Noindex for lowongan post type archive page.
     * - Noindex,nofollow for staging/dev subdomains.
     */
    public static function robotsMeta(array $robots): array
    {
        if (is_post_type_archive('lowongan')) {
            $robots['noindex'] = true;
        }

        if (defined('WP_LOKERBJM_NO_INDEX') && WP_LOKERBJM_NO_INDEX) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    /*======================================================================
     | HEADERS
     ======================================================================*/

    /**
     * Sets X-WP-Nonce header for authenticated users.
     */
    public static function restHeaders(): void
    {
        try {
            if (!is_user_logged_in()) {
                return;
            }

            $nonce = wp_create_nonce('wp_rest');
            header('X-WP-Nonce: ' . $nonce);
        } catch (\Exception $e) {
            Logger::error('Hooks', 'Hooks::restHeaders error: ' . $e->getMessage());
        }
    }

    /**
     * Modifies HTTP headers to remove unwanted Link headers and add sitemap link.
     */
    public static function modifyLinkHeaders(): void
    {
        if (!headers_sent()) {
            // Remove all Link headers to prevent API discovery exposure
            header_remove('Link');

            self::exposeSitemapHeader();
        }
    }

    public static function exposeSitemapHeader(): void
    {
        if (!headers_sent()) {
            $sitemap_url = home_url('/sitemap_index.xml');
            header('Link: <' . esc_url($sitemap_url) . '>; rel="sitemap"');
        }
    }

    /**
     * Exposes specific headers for REST API responses.
     */
    public static function filterRestHeaders($served, $result, $request, $server)
    {
        if (!headers_sent()) {
            header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link, X-WP-Nonce');
        }
        return $served;
    }

    /*======================================================================
     | FILTERS
     ======================================================================*/

    /**
     * Customizes the SQL WHERE clause for WordPress search queries on job posts.
     *
     * This filter intercepts the default WordPress search behavior and replaces it with
     * custom SQL that searches across multiple fields relevant to job listings:
     * - Post titles
     * - Company names (stored in post meta)
     * - Taxonomy terms (e.g., job categories, locations)
     *
     * This enables more comprehensive search results for the job platform, allowing users
     * to find jobs by company name or category even if those terms aren't in the title.
     *
     * Used by: SearchForm, DynamicSearch REST endpoint, and any WP_Query with 's' parameter
     * on 'lowongan' post type.
     *
     * @param string $search The current search SQL fragment (may be empty).
     * @param \WP_Query $wp_query The WP_Query object being executed.
     * @return string Modified search SQL fragment.
     */
    public function jobPostsSearchFilter(string $search, \WP_Query $wp_query): string
    {
        global $wpdb;

        // Get the search query from WP_Query vars
        $q = $wp_query->query_vars['s'] ?? '';

        if ($wpdb !== null && $q !== '') {
            // Delegate to JobQuery for building the custom search SQL
            $search = JobQuery::buildPostsSearchSql($wpdb, $q);
        }

        return $search;
    }

    /*======================================================================
     | ENVIRONMENT FILTERS
     ======================================================================*/

    /**
     * Temporarily disable specific plugins if in development environment.
     */
    public function disablePluginsForDev(array $plugins): array
    {
        $isDev = SharedUtils::isDevelopment();
        if (!$isDev) {
            return $plugins;
        }
        $extra = [
        ];

        $pluginsToDisable = $this->listPluginsToDisable($extra);
        return $this->filteredPlugins($plugins, $pluginsToDisable);
    }

    /**
     * Temporarily disable specific plugins if simulating production environment on local machine.
     */
    public function disablePluginsforSimulatedProd(array $plugins): array
    {
        $isDev = SharedUtils::isDevelopment();
        if (!$isDev) {
            return $plugins;
        }

        $pluginsToDisable = $this->listPluginsToDisable();

        return $this->filteredPlugins($plugins, $pluginsToDisable);
    }

    private function filteredPlugins(array $plugins, array $pluginsToDisable): array
    {
        $filtered = array_filter($plugins, function (string $plugin) use ($pluginsToDisable): bool {
            foreach ($pluginsToDisable as $disable) {
                if (str_starts_with($plugin, $disable)) {
                    return false;
                }
            }
            return true;
        });

        return array_values($filtered);
    }

    private function listPluginsToDisable(?array $extra = []): array
    {
        return array_merge([
            'google-site-kit/',
            'seo-by-rank-math/',
            'fast-indexing-api/',
            'wps-hide-login/',
        ], $extra);
    }

    /*======================================================================
     | CACHE
     ======================================================================*/

    /**
     * Purge query caches when posts or taxonomies are modified.
     */
    private function purgeQueryCaches($post = null): void
    {
        // Only purge for lowongan posts if post is provided
        if ($post && (!is_object($post) || $post->post_type !== PostTypes::POST_TYPE_LOWONGAN)) {
            return;
        }

        try {
            // Purge job last modified cache
            Cache::delete(CacheKey::JOB_LAST_MODIFIED);

            // Purge taxonomy last modified cache
            Cache::delete(CacheKey::TAXONOMY_LAST_MODIFIED);

            // Purge search SQL caches using pattern delete
            Cache::deletePattern(CacheKey::SEARCH_SQL_PREFIX . '*');

            // Purge company search caches
            Cache::deletePattern(CacheKey::COMPANY_SEARCH_PREFIX . '*');

            // Purge auto suggestion caches
            Cache::deletePattern(CacheKey::AUTO_SUGGESTION_PREFIX . '*');

            // Purge load more caches
            Cache::deletePattern(CacheKey::LOAD_MORE_PREFIX . '*');

            // Purge dynamic search caches
            Cache::deletePattern(CacheKey::DYNAMIC_SEARCH_PREFIX . '*');

            // Purge presenter caches
            Cache::delete(CacheKey::CAROUSEL_JOBS);
            Cache::deletePattern(CacheKey::JOB_GRID_PREFIX . '*');

            // Purge taxonomy repository caches
            Cache::delete(CacheKey::ALL_TAXONOMY_TERMS);
            Cache::deletePattern(CacheKey::POST_TAXONOMIES_PREFIX . '*');

            // Purge taxonomy depth REST caches
            Cache::delete(CacheKey::TAXONOMY_DEPTH_HANDLE);
            Cache::delete(CacheKey::TAXONOMY_DEPTH_LOKASI);
            Cache::delete(CacheKey::TAXONOMY_DEPTH_GENDER);
            Cache::delete(CacheKey::TAXONOMY_DEPTH_PENDIDIKAN);

            // Purge theme data cache
            Cache::delete(CacheKey::THEME_DATA);

            // If specific post, also purge its taxonomy cache
            if ($post && is_object($post)) {
                Cache::delete(CacheKey::POST_TAXONOMIES_PREFIX . $post->ID);
            }
        } catch (\Exception $e) {
            Logger::error('Hooks', 'Hooks::purgeQueryCaches error: ' . $e->getMessage());
        }
    }

    /**
     * Clear job data cache when post is saved or deleted.
     */
    public function clearJobDataCache($post_id, $post = null): void
    {
        $this->invalidateJobDataCache($post_id);
    }

    /**
     * Clear job data cache when post meta is updated.
     */
    public function clearJobDataCacheOnMeta($meta_id, $object_id, $meta_key, $_meta_value): void
    {
        $this->invalidateJobDataCache($object_id);
    }

    /**
     * Clear job data cache when object terms are set.
     */
    public function clearJobDataCacheOnTax($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids): void
    {
        $this->invalidateJobDataCache($object_id);
    }

    /**
     * Clear job data cache when post status changes.
     */
    public function clearJobDataCacheOnStatusChange($new_status, $old_status, $post): void
    {
        if ($new_status !== $old_status && $post->post_type === 'lowongan') {
            $this->invalidateJobDataCache($post->ID);
        }
    }

    /**
     * Invalidate job data cache for a specific post if it's a 'lowongan' post type.
     *
     * @param int $post_id The post ID
     * @return bool True if cache was invalidated, false otherwise
     */
    private function invalidateJobDataCache(int $post_id): bool
    {
        $post_type = get_post_type($post_id);
        if ($post_type !== 'lowongan') {
            return false;
        }

        $jobDataCacheKey = CacheKey::JOB_DATA_PREFIX . $post_id;
        $cardCacheKey = CacheKey::REST_CARD_PREFIX . $post_id;
        $overlayCacheKeyLoggedIn = CacheKey::REST_OVERLAY_PREFIX . $post_id . '_logged_in';
        $overlayCacheKeyPublic = CacheKey::REST_OVERLAY_PREFIX . $post_id . '_public';
        $schemaCacheKey = CacheKey::JOB_SCHEMA_PREFIX . $post_id;

        // Use deleteMultiple for better performance - single network round trip
        $cacheKeys = [
            $jobDataCacheKey,
            $cardCacheKey,
            $overlayCacheKeyLoggedIn,
            $overlayCacheKeyPublic,
            $schemaCacheKey,
        ];

        $deleteResults = Cache::deleteMultiple($cacheKeys);

        // Return true if any cache entry was deleted
        return !empty(array_filter($deleteResults));
    }
}