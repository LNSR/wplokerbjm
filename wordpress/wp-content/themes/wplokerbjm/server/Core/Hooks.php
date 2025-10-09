<?php

namespace WPLokerBJM\Core;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Core\Hooks\Theme;
use WPLokerBJM\Core\Hooks\Nonce;
use WPLokerBJM\Core\Hooks\Litespeed;


class Hooks implements HooksInterface
{
    /*======================================================================
     | REGISTER HOOKS
     ======================================================================*/

    public function registerActions(): void
    {
        // Ensure theme features (like custom-logo) are registered early
        add_action('after_setup_theme', [Theme::class, 'addThemeSupport']);
        add_action('wp_enqueue_scripts', [Theme::class, 'unregisterUnneededWPScripts']);
        add_action('wp_head', [Theme::class, 'injectThemeScript'], 3);
        add_action('litespeed_purged_all', [Litespeed::class, 'clearObjectCacheAndTransient'], 20);
        add_action('wp_head', [Nonce::class, 'injectNonceScript']);
        add_action('send_headers', [Nonce::class, 'SendNonceHeader']);
        add_action('template_redirect', [$this, 'redirectToHome'], 0);
    }

    /*======================================================================
     | REGISTER FILTERS
     ======================================================================*/

    public function registerFilters(): void
    {
        add_filter('posts_search', [$this, 'jobPostsSearchFilter'], 10, 2);
        add_filter('litespeed_optimize_js_excludes', [Litespeed::class, 'lscJsExcludes'], 0);
        add_filter('litespeed_optimize_css_excludes', [Litespeed::class, 'lscCssExcludes'], 0);
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
    public function jobPostsSearchFilter($search, $wp_query)
    {
        global $wpdb;
        if (!is_object($wpdb)) {
            // $wpdb is not available, return original search
            return $search;
        }
        if (!empty($wp_query->query_vars['s'])) {
            $q = $wp_query->query_vars['s'];
            $search = \WPLokerBJM\QueryBuilders\JobQuery::buildPostsSearchSql($wpdb, $q);
        }
        return $search;
    }

    /**
     * Temporarily disable specific plugins if in development environment.
     */
    public function disablePluginsForDev($plugins)
    {
        $isDev = getenv('WP_ENV') === 'development';
        if ($isDev) {
            $pluginsToDisable = [
                'google-site-kit/',
                'seo-by-rank-math/',
                'fast-indexing-api/',
                'wps-hide-login/',
                'litespeed-cache/',
            ];
            return array_filter($plugins, function ($plugin) use ($pluginsToDisable) {
                foreach ($pluginsToDisable as $disable) {
                    if (strpos($plugin, $disable) === 0) {
                        return false;
                    }
                }
                return true;
            });
        }
        return $plugins;
    }
}