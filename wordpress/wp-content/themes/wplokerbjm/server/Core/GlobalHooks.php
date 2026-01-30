<?php

namespace WPLokerBJM\Core;

use WPLokerBJM\Core\Theme\Enqueue;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};

/**
 * Global hooks registration for actions and filters.
 * Registers WordPress actions and filters to modify theme behavior.
 * * Might dump some temporary hooks here before promoting to dedicated classes/layer.
 */
class GlobalHooks
{
    /*======================================================================
     | REDIRECTS
     ======================================================================*/

    /**
     * Return 410 Gone for old job posts that have been trashed due to age.
     * * For deleted job posts (404 on single lowongan), return 410 Gone.
     * ! Notify search engines with 410 Gone for removed job posts.
     */
    #[Action('template_redirect', 1)]
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
            if (is_404()) {
                status_header(410);
                wp_die('This job posting has been expired or removed.', 'Gone', ['response' => 410]);
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
    #[Action('template_redirect', 3)]
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
    #[Filter('wp_robots')]
    public static function robotsMetaImpl(array $robots): array
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
     * Attempts to authenticate the user via the logged-in cookie if not already authenticated.
     */
    private static function authenticateViaCookie(): void
    {
        if (!is_user_logged_in()) {
            if (function_exists('wp_validate_auth_cookie') && defined('LOGGED_IN_COOKIE') && isset($_COOKIE[LOGGED_IN_COOKIE])) {
                $cookie = $_COOKIE[LOGGED_IN_COOKIE];
                $user_id = wp_validate_auth_cookie($cookie, 'logged_in');
                if ($user_id) {
                    // Ensure current user is populated for downstream is_user_logged_in()/nonce creation
                    wp_set_current_user((int) $user_id);
                } else {
                    // No valid cookie -> nothing to expose
                    return;
                }
            } else {
                return;
            }
        }
    }

    /**
     * Modifies HTTP headers to remove unwanted Link headers and add sitemap link.
     */
    #[Action('template_redirect', 11)]
    public static function modifyLinkHeadersImpl(): void
    {
        if (!headers_sent()) {
            // Remove all Link headers to prevent API discovery exposure
            header_remove('Link');
            Enqueue::outputPreloadLinksResponse();
        }
    }

    #[Action('wp_head')]
    public static function exposeSitemapHeader(): void
    {
        $sitemap_url = home_url('/sitemap_index.xml');
        echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="' . esc_url($sitemap_url) . '" />' . "\n";
    }

    /**
     * Restricts GraphQL CORS to same origin for security and adds X-WP-Nonce for logged-in users.
     */
    #[Filter('graphql_response_headers_to_send')]
    public static function ModifyHeaderGraphQL(array $headers): array
    {
        // Get the site's origin
        $site_url = home_url();
        $parsed = wp_parse_url($site_url);
        $site_origin = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port']) && $parsed['port'] !== '80' && $parsed['port'] !== '443') {
            $site_origin .= ':' . $parsed['port'];
        }

        // Set CORS to same origin only
        $headers['Access-Control-Allow-Origin'] = $site_origin;
        $headers['Access-Control-Allow-Credentials'] = 'true'; // Required for cookies/nonces

        self::authenticateViaCookie();

        if (is_user_logged_in()) {
            $headers['Access-Control-Allow-Headers'] .= ', X-WP-Nonce';
            $headers['Access-Control-Expose-Headers'] = 'X-WP-Nonce';
            $headers['X-WP-Nonce'] = wp_create_nonce('wp_rest');
        }

        return $headers;
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
    #[Filter('posts_search', 10, 2)]
    public function jobPostsSearchFilterImpl(string $search, \WP_Query $wp_query): string
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
    #[Filter('option_active_plugins', 0)]
    public function disablePluginsForDevImpl(array $plugins): array
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
    #[Filter('option_active_plugins', 0)]
    public function disablePluginsforSimulatedProdImpl(array $plugins): array
    {
        $isDev = !SharedUtils::isDevelopment() && SharedUtils::isLocalhost();
        if (!$isDev) {
            Logger::info('Hooks', 'Not simulating production environment; no plugins disabled.');
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
            'wpgraphql-smart-cache/',
            'tinywp-mobile-detect/',
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
    #[Action('save_post', 10, 2)]
    #[Action('delete_post', 10, 1)]
    #[Action('trashed_post', 10, 1)]
    #[Action('delete_attachment', 10, 1)]
    #[Action('created_term', 10, 0)]
    #[Action('edited_term', 10, 0)]
    #[Action('delete_term', 10, 0)]
    #[Action('updated_post_meta', 10, 4)]
    #[Action('set_object_terms', 10, 6)]
    #[Action('transition_post_status', 10, 3)]
    public function purgeCacheOnChange(...$args): void
    {
        // Normalize incoming hook args: find first numeric-like value as post_id and any \WP_Post instance as post
        $post_id = null;
        $post = null;

        foreach ($args as $arg) {
            if ($arg instanceof \WP_Post) {
                $post = $arg;
                $post_id = $post->ID ?? $post_id;
                // keep scanning in case a numeric ID also appears elsewhere
                continue;
            }

            if (is_int($arg)) {
                $post_id = $arg;
                continue;
            }

            if (is_string($arg) && ctype_digit($arg)) {
                $post_id = (int) $arg;
                continue;
            }
        }
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
                CacheKey::HOMEPAGE_JOB_SCHEMAS,
            ]);

            Cache::deletePattern([
                CacheKey::JOB_GRID_PREFIX . '*',
                CacheKey::SEARCH_SQL_PREFIX . '*',
                CacheKey::COMPANY_SEARCH_PREFIX . '*',
                CacheKey::AUTO_SUGGESTION_PREFIX . '*',
                CacheKey::POST_TAXONOMIES_PREFIX . '*',
                CacheKey::GRAPHQL_JOB_DETAIL_PREFIX . '*',
                CacheKey::GRAPHQL_JOB_CARD_PREFIX . '*',
                CacheKey::DYNAMIC_SEARCH_PREFIX . '*',
                CacheKey::SYNC_BOOKMARK_PREFIX . '*',
                CacheKey::GRAPHQL_JOB_SCHEMA_BATCH_PREFIX . '*',
                CacheKey::RANKMATH_HEAD_PREFIX . '*',
                CacheKey::THEME_DATA . '*'
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
        $cardCacheKey = CacheKey::GRAPHQL_JOB_CARD_PREFIX . $post_id;
        // overlay caches may be per-user or public; we'll invalidate by pattern below
        $schemaCacheKey = CacheKey::JOB_SCHEMA_PREFIX . $post_id;

        // Use deleteMultiple for better performance - single network round trip
        $cacheKeys = [
            $jobDataCacheKey,
            $cardCacheKey,
            $schemaCacheKey,
            $schemaCacheKey,
        ];

        $deleteResults = Cache::deleteMultiple($cacheKeys);

        // Return true if any cache entry was deleted
        return !empty(array_filter($deleteResults));
    }
}