<?php

namespace WPLokerBJM\Core;

use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\PluginList;

/*======================================================================
 | Collection of Global Hooks Classes
 ======================================================================*/

/*======================================================================
 | REDIRECTS
 ======================================================================*/

/**
 * Handles all template_redirect hooks: SSL bypass, 410 Gone, archive → home,
 * and headless SvelteKit frontend routing.
 */
class RedirectHooks
{
    /**
     * Skip redirect logic for ACME challenge requests and AutoSSL probe user agents.
     */
    private static function shouldBypassAutoSsl(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($requestUri !== '' && str_starts_with($requestUri, '/.well-known/acme-challenge/')) {
            return true;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($userAgent === '') {
            return false;
        }

        return stripos($userAgent, 'litespeed') !== false || stripos($userAgent, 'quic-cloud') !== false;
    }

    /**
     * Common early-return guard for all template_redirect hooks.
     *
     * Consolidates checks that apply universally: SSL bypass probes,
     * backend-only contexts (REST, AJAX, CLI, cron, preview), and
     * special query-parameter bypass flags.
     */
    private static function shouldSkipRedirect(): bool
    {
        if (self::shouldBypassAutoSsl()) {
            return true;
        }

        if (
            wp_doing_cron() ||
            is_preview()
        ) {
            return true;
        }

        return false;
    }

    private static function shouldRegister(): bool
    {

        if (
            (defined('GRAPHQL_REQUEST') && GRAPHQL_REQUEST) ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            (defined('DOING_AJAX') && DOING_AJAX) ||
            SharedUtils::isWPCLI() ||
            is_admin() ||
            isset($_GET['_wfsf']) // WordFence query
        ) {
            return false;
        }

        return true;
    }

    /**
     * Return 410 Gone for old job posts that have been trashed due to age.
     * * For deleted job posts (404 on single lowongan), return 410 Gone.
     * ! Notify search engines with 410 Gone for removed job posts.
     */
    #[Action('template_redirect', 2,
        once: true,
        registerIf: static function (): bool {
                return self::shouldRegister();
                },
        executeIf: static function (): bool {
                return !self::shouldSkipRedirect() && is_404() && is_singular('lowongan');
                },
    )]
    public function oldPost410Redirect(): void
    {
        status_header(410);
        wp_die('This job posting has been expired or removed.', 'Gone', ['response' => 410]);

        // Other 404s: redirect to the headless Svelte frontend
        $baseUrl = SharedUtils::headlessDomainRedirect();
        wp_redirect(rtrim($baseUrl, '/') . '/', 302);
        exit;
    }

    /**
     * Headless frontend redirect.
     * Runs early during `template_redirect` so the theme always forwards
     * public requests to the Svelte frontend (dev vs prod).
     */
    #[Action('template_redirect', 3,
        once: true,
        registerIf: static function (): bool {
                return self::shouldRegister();
                },
        executeIf: static function (): bool {
                return !self::shouldSkipRedirect();
                },
    )]
    public function headlessFrontendAdminSideRedirect(): void
    {
        $baseUrl = SharedUtils::headlessDomainRedirect();

        $path = '/';
        if ((is_page('pasang-iklan-loker') || is_page(184))) {
            $path = '/pasang-iklan-loker';
        } elseif (is_page('kebijakan-privasi')) {
            $path = '/kebijakan-privasi';
        } elseif (is_single() && get_post_type() === 'lowongan') {
            $post = get_post();
            if ($post && !empty($post->post_name)) {
                $path = '/lowongan/' . $post->post_name;
            }
        } elseif (is_post_type_archive('lowongan') || is_front_page() || is_page(146)) {
            $path = '/';
        }

        $query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
        $location = rtrim($baseUrl, '/') . $path . $query;

        wp_redirect($location, 302);
        exit;
    }
}


/*======================================================================
 | META TAGS / SEO
 ======================================================================*/

/**
 * Adds noindex and nofollow directives to robots meta tag.
 * - Noindex for lowongan post type archive page.
 * - Noindex,nofollow for staging/dev subdomains.
 */
class RobotsHooks
{
    #[Filter('wp_robots')]
    public function __invoke(array $robots): array
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
}


/*======================================================================
 | SEARCH
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
 * Used by: DynamicSearch Graphql endpoint, and any WP_Query with 's' parameter
 * on 'lowongan' post type.
 */
class SearchHooks
{

    /**
     * @param string        $search   The current search SQL fragment (may be empty).
     * @param \WP_Query     $wp_query The WP_Query object being executed.
     * @return string Modified search SQL fragment.
     */
    #[Filter('posts_search', 10, 2,
        deferRegisterUntilHook: 'init_graphql_request',
        registerIf: static function (): bool {
                return !is_admin() && !SharedUtils::isWPCLI();
                },
        executeIf: static function (\WP_Query $wp_query): bool {
                $postTypes = (array) ($wp_query->get('post_type') ?: []);
                return in_array(PostTypes::POST_TYPE_LOWONGAN, $postTypes, true);
                }

    )]
    public function jobPostsSearchFilterImpl(string $search, \WP_Query $wp_query): string
    {
        global $wpdb;
        $q = (string) ($wp_query->query_vars['s'] ?? '');
        if ($wpdb === null || $q === '') {
            return $search;
        }

        return JobQuery::buildPostsSearchSql($wpdb, $q);
    }
}


/*======================================================================
 | LANGUAGE HOOKS
 ======================================================================*/

/**
 * Force locale to Indonesian on the frontend for consistent user experience,
 * while keeping admin in English.
 */
class LanguageHooks
{
    #[Filter(
        'locale',
        registerIf: static function (): bool {
                return !is_admin();
                }
    )]
    public function frontendLocalHTMLl10n($locale)
    {
        return $locale = 'id_ID';
    }
}

/*======================================================================
 | HTTP HOOKS
 ======================================================================*/
class HTTPHooks
{
    //** forwarded IP from the SvelteKit frontend
    #[Action(
        'muplugins_loaded',
        PHP_INT_MIN,
        registerIf: static function (): bool {
                return !SharedUtils::isDevelopment() && !SharedUtils::isWPCLI();
                }
    )]
    public function setRemoteAddr(): void
    {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
            return;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $_SERVER['REMOTE_ADDR'] = trim($ips[0]);
        }
    }
}

/*======================================================================
 | CACHE INVALIDATION
 ======================================================================*/

/**
 * Centralized cache purge when posts, meta, terms, or status change.
 *
 * Calling conventions:
 * - For post hooks (e.g., `save_post`, `delete_post`) we pass `($post_id, $post)` so
 *   the method can perform both global purges and per-job invalidation when applicable.
 * - For term hooks (`created_term`, `edited_term`, `delete_term`) we call this method
 *   without arguments (no post context) and it will only purge global caches.
 * - For meta/taxonomy hooks we pass the `object_id` (post id) when available.
 */
class CacheInvalidationHooks
{

    /**
     * @param WPHooksContainerRegistry $hooksRegistry Used for self-unregistration of the
     *                                       global purge after first fire per request.
     * @param RedisAdapter    $redisAdapter  Used for direct Redis pattern-based cache deletion.
     */
    public function __construct(
        private WPHooksContainerRegistry $hooksRegistry,
        private RedisAdapter $redisAdapter,
    ) {

    }

    /**
     * Per-post cache invalidation — fires for EVERY lowongan post change.
     *
     * Never self-unregisters: in a batch of 50 trashed jobs, each one must
     * invalidate its own individual cache entries. The global cache sweep
     * is handled separately by {@see purgeGlobalCacheOnce()}.
     *
     * Registered only on hooks that carry post context.
     */
    #[Action('save_post_' . PostTypes::POST_TYPE_LOWONGAN, 10, 2)]
    #[Action('delete_post_' . PostTypes::POST_TYPE_LOWONGAN, 10, 1)]
    #[Action('trashed_post', 10, 1)]
    #[Action('delete_attachment', 10, 1)]
    #[Action('transition_post_status', 10, 3)]
    public function invalidatePostCache(...$args): void
    {
        static $_snapshot_postId;
        $post_id = $this->extractPostId($args);
        if ((isset($_snapshot_postId) && $_snapshot_postId === $post_id) || $post_id === null) {
            return;
        }
        $_snapshot_postId = $post_id;

        $this->invalidateJobDataCache((int) $post_id);
    }

    /**
     * Global cache purge — fires once per request, then self-unregisters.
     *
     * One comprehensive global sweep per request is sufficient. After the
     * first fire, this handler removes itself from WordPress so that term/
     * meta changes later in the same request don't trigger redundant purges.
     */
    #[Action('save_post_' . PostTypes::POST_TYPE_LOWONGAN, 10, 2)]
    #[Action('delete_post_' . PostTypes::POST_TYPE_LOWONGAN, 10, 1)]
    #[Action('trashed_post', 10, 1)]
    #[Action('delete_attachment', 10, 1)]
    #[Action('created_term', 10, 0)]
    #[Action('edited_term', 10, 0)]
    #[Action('delete_term', 10, 0)]
    #[Action('updated_postmeta', 10, 4)]
    #[Action('set_object_terms', 10, 6)]
    #[Action('transition_post_status', 10, 3)]
    public function purgeGlobalCacheOnce(...$args): void
    {
        static $alreadyRun = false;
        if ($alreadyRun)
            return;
        $alreadyRun = true;
        $this->hooksRegistry->unregisterByCallable([$this, __FUNCTION__]);

        try {
            Cache::deleteMultiple([
                CacheKey::CAROUSEL_JOBS,
                CacheKey::JOB_LAST_MODIFIED,
                CacheKey::TAXONOMY_DEPTH_HANDLE,
                CacheKey::TAXONOMY_DEPTH_LOKASI,
                CacheKey::TAXONOMY_DEPTH_GENDER,
                CacheKey::TAXONOMY_DEPTH_PENDIDIKAN,
            ]);

            $this->redisAdapter->deletePattern([
                CacheKey::JOB_GRID_PREFIX . '*',
                CacheKey::SEARCH_SQL_PREFIX . '*',
                CacheKey::LOAD_MORE_PREFIX . '*',
                CacheKey::AUTO_SUGGESTION_PREFIX . '*',
                CacheKey::POST_TAXONOMIES_PREFIX . '*',
                CacheKey::GRAPHQL_JOB_DETAIL_PREFIX . '*',
                CacheKey::GRAPHQL_JOB_CARD_PREFIX . '*',
                CacheKey::DYNAMIC_SEARCH_PREFIX . '*',
                CacheKey::SYNC_BOOKMARK_PREFIX . '*',
                CacheKey::GRAPHQL_JOB_SCHEMA_BATCH_PREFIX . '*',
                CacheKey::RANKMATH_HEAD_PREFIX . '*',
                CacheKey::GRAPHQL_ETAG_PREFIX . '*',
                CacheKey::THEME_DATA . '*',
            ]);
        } catch (\Exception $e) {
            Logger::error('Hooks', 'CacheInvalidationHooks::purgeGlobalCacheOnce error: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    /**
     * Extract a post ID from variadic hook arguments, validating post type.
     *
     * @param array $args Hook arguments
     * @return int|null The resolved lowongan post ID, or null if not applicable
     */
    private function extractPostId(array $args): ?int
    {
        $post_id = null;

        foreach ($args as $arg) {
            if ($arg instanceof \WP_Post) {
                if ($arg->post_type !== PostTypes::POST_TYPE_LOWONGAN) {
                    return null;
                }
                $post_id = $arg->ID;
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

        if ($post_id !== null) {
            $resolved = get_post($post_id);
            if ($resolved === null || $resolved->post_type !== PostTypes::POST_TYPE_LOWONGAN) {
                return null;
            }
        }

        return $post_id;
    }

    /**
     * Invalidate job data caches for a specific lowongan post.
     *
     * @param int $post_id The post ID.
     * @return bool True if any cache entry was deleted.
     */
    private function invalidateJobDataCache(int $post_id): bool
    {
        $deleteResults = Cache::deleteMultiple([
            CacheKey::JOB_DATA_PREFIX . $post_id,
            CacheKey::GRAPHQL_JOB_CARD_PREFIX . $post_id,
            CacheKey::JOB_SCHEMA_PREFIX . $post_id,
        ]);

        return !empty(array_filter($deleteResults));
    }
}


/*======================================================================
 | LOGGER FLUSH
 ======================================================================*/

/**
 * Flushes the Logger's in-memory buffer on WordPress shutdown.
 *
 * All Logger::info/debug/warning/error calls during the request are
 * buffered in memory. This handler writes them to error_log() in a
 * single batch when the request completes, with a graceful fallback
 * to individual writes on failure.
 */
class LoggerHooks
{

    #[Action('shutdown', PHP_INT_MAX, once: true)]
    public function flushBuffer(): void
    {
        // ONLY detach the browser connection if we are running in an HTTP context (not WP-CLI)
        if (defined('PHP_SAPI') && PHP_SAPI !== 'cli') {
            if (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            } elseif (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }
        Logger::flush();
    }
}
