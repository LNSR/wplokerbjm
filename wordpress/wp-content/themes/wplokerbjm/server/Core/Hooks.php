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
    /**
     * Constructor for Hooks class.
     *
     * @param BotDetection $botDetection Service for bot detection.
     * @param PostsCRUDListener $postsCRUDListener Listener for post CRUD operations.
     * @param RedirectToSSG $redirectToSSG Service for SSG redirects.
     * @param RESTRoute $restRoute Service for REST API routes.
     */
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

    /**
     * Registers WordPress action hooks.
     *
     * Sets up various action hooks for theme functionality, caching, SSG, and more.
     *
     * @return void
     */
    public function registerActions(): void
    {
        add_action('after_setup_theme', fn() => ThemeInject::addThemeSupport(), 0);
        add_action('wp_footer', fn() => ThemeInject::injectThemeScript(), 0);
        add_action('wp_head', fn() => Enqueue::outputPreloadLinks(), 0);
        add_action('wp_head', fn() => ThemeInject::preloadLogo(), 0);
        add_action('wp_enqueue_scripts', fn() => DebloatWPTheme::removeWPLibrary(), 4);
        add_action('litespeed_purged_all', fn() => Litespeed::clearObjectCache());
        add_action('wp_enqueue_scripts', fn() => Enqueue::enqueueAssets(), 0);
        add_action('template_redirect', fn() => $this->oldPost410Redirect(), 4);
        add_action('template_redirect', fn() => self::redirectToHome(), 4);
        add_action('template_redirect', fn() => self::modifyLinkHeaders(), 12);
        add_action('send_headers', fn() => self::restHeaders(), 12);

        // API registerActions
        add_action('rest_api_init', fn() => $this->restRoute->registerRoutes(), 0);

        // Cache purging hooks
        // Note: we request only the args we need from WP to avoid unused parameter warnings.
        add_action('save_post', fn($post_id, $post) => $this->purgeCacheOnChange($post_id, $post), 10, 2);
        add_action('delete_post', fn($post_id) => $this->purgeCacheOnChange($post_id), 10, 1);
        add_action('trashed_post', fn($post_id) => $this->purgeCacheOnChange($post_id), 10, 1);
        add_action('delete_attachment', fn($post_id) => $this->purgeCacheOnChange($post_id), 10, 1);
        add_action('created_term', fn() => $this->purgeCacheOnChange(), 10, 0);
        add_action('edited_term', fn() => $this->purgeCacheOnChange(), 10, 0);
        add_action('delete_term', fn() => $this->purgeCacheOnChange(), 10, 0);
        add_action('updated_post_meta', fn($meta_id, $object_id, $meta_key, $_meta_value) => $this->purgeCacheOnChange($object_id), 10, 4);
        add_action('set_object_terms', fn($object_id) => $this->purgeCacheOnChange($object_id), 10, 6);
        add_action('transition_post_status', fn($_new_status, $_old_status, $post) => $this->purgeCacheOnChange($post->ID ?? null, $post), 10, 3);

        // SSG hooks
        add_action('save_post', fn(...$args) => $this->postsCRUDListener->onSavePost(...$args), 10, 3);
        add_action('before_delete_post', fn(...$args) => $this->postsCRUDListener->onBeforeDeletePost(...$args), 1, 1);
        add_action('wp_trash_post', fn(...$args) => $this->postsCRUDListener->onTrashPost(...$args), 1, 1);
        add_action('template_redirect', fn() => $this->redirectToSSG->serveSSG(), 0);
        add_action('send_headers', fn() => $this->redirectToSSG->buildHeaders(), 10);
        add_action('wp_footer', fn() => $this->redirectToSSG->setCookieToHuman(), 10);
    }

    /*======================================================================
     | REGISTER FILTERS
     ======================================================================*/

    /**
     * Registers WordPress filter hooks.
     *
     * Sets up various filter hooks for modifying WordPress behavior, SEO, plugins, and search.
     *
     * @return void
     */
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

    /**
     * Filters the list of active plugins by removing specified plugins.
     *
     * @param array $plugins Array of active plugin file paths.
     * @param array $pluginsToDisable Array of plugin prefixes to disable.
     * @return array Filtered array of active plugins.
     */
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

    /**
     * Returns the list of plugins to disable, optionally merged with extra plugins.
     *
     * @param array|null $extra Optional array of additional plugin prefixes to disable.
     * @return array Array of plugin prefixes to disable.
     */
    private function listPluginsToDisable(?array $extra = []): array
    {
        return array_merge([
            // 'google-site-kit/',
            'fast-indexing-api/',
            'wps-hide-login/',
        ], $extra);
    }

    /*======================================================================
     | CACHE
     ======================================================================*/

    /**
     * Centralized cache purge when posts, meta, terms, or status change.
     *
     * Prefer explicit parameters to make intent and types clear when hooked from WP.
     *
     * Calling conventions:
     * - For post hooks (e.g., `save_post`, `delete_post`) we pass `($post_id, $post)` so
     *   the method can perform both global purges and per-job invalidation when applicable.
     * - For term hooks (`created_term`, `edited_term`, `delete_term`) we call this method
     *   without arguments (no post context) and it will only purge global caches.
     * - For meta/taxonomy hooks we pass the `object_id` (post id) when available.
     *
     * @param int|null $post_id Optional post ID when available (null for term-level hooks).
     * @param \WP_Post|null $post Optional WP_Post object when available.
     * @return void
     */
    public function purgeCacheOnChange(?int $post_id = null, $post = null): void
    {
        // If a post object is provided, ensure it's the 'lowongan' type; otherwise bail out.
        if ($post instanceof \WP_Post) {
            if ($post->post_type !== PostTypes::POST_TYPE_LOWONGAN) {
                return;
            }
            $post_id = $post->ID ?? $post_id;
        }

        // If only a post_id was provided, resolve it and apply same post type check.
        if ($post_id !== null && !($post instanceof \WP_Post)) {
            $resolved = get_post($post_id);
            if ($resolved !== null && $resolved->post_type !== PostTypes::POST_TYPE_LOWONGAN) {
                return; // don't purge when a non-lowongan post changed
            }
            if ($resolved !== null) {
                $post = $resolved;
            }
        }

        try {
            // Purge general caches
            Cache::deleteMultiple([
                CacheKey::CAROUSEL_JOBS,
                CacheKey::JOB_LAST_MODIFIED,
                CacheKey::TAXONOMY_LAST_MODIFIED,
                CacheKey::ALL_TAXONOMY_TERMS,

                    // Purge taxonomy depth REST caches
                CacheKey::TAXONOMY_DEPTH_HANDLE,
                CacheKey::TAXONOMY_DEPTH_LOKASI,
                CacheKey::TAXONOMY_DEPTH_GENDER,
                CacheKey::TAXONOMY_DEPTH_PENDIDIKAN,
            ]);

            Cache::deletePattern([
                CacheKey::JOB_GRID_PREFIX . '*',
                CacheKey::SEARCH_SQL_PREFIX . '*',
                CacheKey::COMPANY_SEARCH_PREFIX . '*',
                CacheKey::AUTO_SUGGESTION_PREFIX . '*',
                CacheKey::POST_TAXONOMIES_PREFIX . '*'
            ]);

            // If we detected a post id, also invalidate the per-job caches
            if ($post_id !== null) {
                $this->invalidateJobDataCache((int) $post_id);
            }
        } catch (\Exception $e) {
            Logger::error('Hooks', 'Hooks::purgeCacheOnChange error: ' . $e->getMessage());
        }
    }

    /**
     * Invalidate job data cache for a specific post if it's a 'lowongan' post type.
     *
     * @param int $post_id The post ID.
     * @return bool True if cache was invalidated, false otherwise.
     */
    private function invalidateJobDataCache(int $post_id): bool
    {
        $post_type = get_post_type($post_id);
        if ($post_type !== 'lowongan') {
            return false;
        }

        $jobDataCacheKey = CacheKey::JOB_DATA_PREFIX . $post_id;
        $cardCacheKey = CacheKey::REST_CARD_PREFIX . $post_id;
        $overlayCacheKeyLoggedIn = CacheKey::REST_JOBDETAIL_PREFIX . $post_id . '_logged_in';
        $overlayCacheKeyPublic = CacheKey::REST_JOBDETAIL_PREFIX . $post_id . '_public';
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

        Cache::deletePattern([CacheKey::REST_JOB_SCHEMA_BATCH_PREFIX . '*']);

        // Return true if any cache entry was deleted
        return !empty(array_filter($deleteResults));
    }
}