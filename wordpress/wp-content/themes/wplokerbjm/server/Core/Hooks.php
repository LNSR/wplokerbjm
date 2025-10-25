<?php

namespace WPLokerBJM\Core;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Core\Hooks\Theme\{Enqueue, ThemeInject, DebloatWPTheme, Google};
use WPLokerBJM\Core\Hooks\{LiteSpeedFilters, Litespeed};
use WPLokerBJM\Services\Utilities\Utilities;

class Hooks implements HooksInterface
{
    /*======================================================================
     | REGISTER HOOKS
     ======================================================================*/

    public function registerActions(): void
    {
        add_action('after_setup_theme', [ThemeInject::class, 'addThemeSupport']);
        add_action('wp_head', [Google::class, 'addGTM'], 0);
        add_action('wp_head', [ThemeInject::class, 'injectThemeScript'], 0);
        add_action('wp_enqueue_scripts', [DebloatWPTheme::class, 'removeJquery']);
        add_action('wp_enqueue_scripts', [DebloatWPTheme::class, 'removeWPLibrary'], 20);
        add_action('litespeed_purged_all', [LiteSpeed::class, 'clearObjectCacheAndTransient']);
        add_action('wp_enqueue_scripts', [Enqueue::class, 'enqueueAssets']);
        add_action('template_redirect', [$this, 'redirectToHome'], 0);
        add_action('template_redirect', [$this, 'oldPost410Redirect'], 1);
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
        add_filter('option_active_plugins', [$this, 'disablePluginsforSimulatedProd'], 0);
    }

    /*======================================================================
     | Actions
     ======================================================================*/


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
        if (is_404()) {
            if (is_singular('lowongan')) {
                // Check if the post exists in trash (deleted due to age)
                global $wp_query;
                $post_name = $wp_query->query_vars['name'] ?? '';
                if ($post_name) {
                    $trashed_post = get_posts(\WPLokerBJM\QueryBuilders\JobQuery::getTrashedJobByNameArgs($post_name));
                    if (!empty($trashed_post)) {
                        wp_die('This job posting has been removed.', 'Gone', ['response' => 410]);
                    }
                }
                // If not in trash or no name, redirect to home (fallback)
                wp_safe_redirect(home_url('/'), 302);
                exit;
            } else {
                // Other 404s redirect to home
                wp_safe_redirect(home_url('/'), 302);
                exit;
            }
        }
    }

    /**
     * Redirect to home if accessing the lowongan post type archive or a 404 page.
     */
    public function redirectToHome(): void
    {
        // Avoid redirecting during admin, AJAX, REST API, or cron requests
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || (function_exists('wp_doing_ajax') && wp_doing_ajax()) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
            return;
        }

        if (is_post_type_archive('lowongan')) {
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

        $pluginsToDisable = array_merge($this->listPluginsToDisable(), ['litespeed-cache/']);
        return $this->filteredPlugins($plugins, $pluginsToDisable);
    }

    /**
     * Temporarily disable specific plugins if simulating production environment on local machine.
     */
    public function disablePluginsforSimulatedProd(array $plugins): array
    {
        $isProdSimulated = getenv('WP_ENV') === 'production' && Utilities::isLocalhost();
        if (!$isProdSimulated) {
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

    private function listPluginsToDisable(): array
    {
        return [
            'google-site-kit/',
            'seo-by-rank-math/',
            'fast-indexing-api/',
            'wps-hide-login/',
        ];
    }

}