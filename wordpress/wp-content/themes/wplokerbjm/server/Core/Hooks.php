<?php

namespace WPLokerBJM\Core;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Core\Hooks\Theme\{Enqueue, ThemeInject, UnregisterWPBloat};
use WPLokerBJM\Core\Hooks\{LiteSpeedFilters, Litespeed};

class Hooks implements HooksInterface
{
    /*======================================================================
     | REGISTER HOOKS
     ======================================================================*/

    public function registerActions(): void
    {
        add_action('after_setup_theme', [ThemeInject::class, 'addThemeSupport']);
        add_action('wp_head', [ThemeInject::class, 'injectThemeScript'], 0);
        add_action('wp_enqueue_scripts', [UnregisterWPBloat::class, 'unregisterJquery']);
        add_action('wp_enqueue_scripts', [UnregisterWPBloat::class, 'unregisterUnneededWPStyles']);
        add_action('litespeed_purged_all', [LiteSpeed::class, 'clearObjectCacheAndTransient']);
        add_action('wp_enqueue_scripts', [Enqueue::class, 'enqueueAssets']);
        add_action('template_redirect', [$this, 'redirectToHome'], 0);
    }

    /*======================================================================
     | REGISTER FILTERS
     ======================================================================*/

    public function registerFilters(): void
    {
        add_filter('style_loader_tag', [Enqueue::class, 'filterStyleLoaderTag'], 10, 2);
        add_filter('posts_search', [$this, 'jobPostsSearchFilter'], 10, 2);
        add_filter('litespeed_optimize_js_excludes', [LiteSpeedFilters::class, 'lscJsExcludes'], 0);
        add_filter('litespeed_optimize_css_excludes', [LiteSpeedFilters::class, 'lscCssExcludes'], 0);
        add_filter('option_active_plugins', [$this, 'disablePluginsForDev'], 0);
    }

    /*======================================================================
     | REDIRECTS
     ======================================================================*/

    /**
     * Redirect to home if accessing the lowongan post type archive or a 404 page.
     */
    public function redirectToHome(): void
    {
        // Avoid redirecting during admin, AJAX, REST API, or cron requests
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || (function_exists('wp_doing_ajax') && wp_doing_ajax()) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
            return;
        }

        if (is_post_type_archive('lowongan') || is_404()) {
            // Use wp_safe_redirect to ensure the redirect target is allowed
            wp_safe_redirect(home_url('/'), 302);
            exit;
        }
    }

    /*======================================================================
     | FILTERS
     ======================================================================*/

    /**
     * Customizes the SQL WHERE clause for WordPress search queries on job posts.
     */
    public function jobPostsSearchFilter(string $search, \WP_Query $wp_query): string
    {
        global $wpdb;
        if ($wpdb === null) {
            return $search;
        }

        $q = $wp_query->query_vars['s'] ?? '';
        if ($q !== '') {
            $search = \WPLokerBJM\QueryBuilders\JobQuery::buildPostsSearchSql($wpdb, $q);
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
        $isDev = getenv('WP_ENV') === 'development';
        if (!$isDev) {
            return $plugins;
        }

        $pluginsToDisable = [
            'google-site-kit/',
            'seo-by-rank-math/',
            'fast-indexing-api/',
            'wps-hide-login/',
            'litespeed-cache/',
        ];

        // Keep plugins that do NOT start with any of the items in $pluginsToDisable
        $filtered = array_filter($plugins, function (string $plugin) use ($pluginsToDisable): bool {
            foreach ($pluginsToDisable as $disable) {
                if (str_starts_with($plugin, $disable)) {
                    return false;
                }
            }
            return true;
        });

        // reindex array to ensure a clean numerically indexed array
        return array_values($filtered);
    }
}