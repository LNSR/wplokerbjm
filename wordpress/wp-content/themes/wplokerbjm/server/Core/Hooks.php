<?php

namespace WPLokerBJM\Core;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Core\Hooks\Theme\{Enqueue, ThemeInject, DebloatWPTheme, Google};
use WPLokerBJM\Core\Hooks\{LiteSpeedFilters, Litespeed};
use WPLokerBJM\Services\Utilities\Utilities;
class Hooks implements HooksInterface
{
    public function __construct(private \WPLokerBJM\Services\Utilities\SSG\BotDetection $botDetection)
    {
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
        add_action('wp_enqueue_scripts', fn() => DebloatWPTheme::removeWPLibrary(), 0);
        add_action('litespeed_purged_all', fn() => Litespeed::clearObjectCache());
        add_action('wp_enqueue_scripts', fn() => Enqueue::enqueueAssets());
        add_action('template_redirect', fn() => $this->oldPost410Redirect());
        add_action('template_redirect', fn() => $this->redirectToHome());
        add_action('template_redirect', fn() => $this->modifyLinkHeaders(), 15);
        add_action('send_headers', fn() => $this->restHeaders());
    }

    /*======================================================================
     | REGISTER FILTERS
     ======================================================================*/

    public function registerFilters(): void
    {
        add_filter('wp_robots', fn(...$args) => $this->robotsMeta(...$args));
        add_filter('rest_pre_serve_request', fn(...$args) => $this->filterRestHeaders(...$args), 10, 4);
        add_filter('litespeed_optimize_js_excludes', fn(...$args) => LiteSpeedFilters::lscJsExcludes(...$args), 0);
        add_filter('litespeed_optimize_css_excludes', fn(...$args) => LiteSpeedFilters::lscCssExcludes(...$args), 0);
        add_filter('option_active_plugins', fn(...$args) => $this->disablePluginsForDev(...$args), 0);
        add_filter('option_active_plugins', fn(...$args) => $this->disablePluginsforSimulatedProd(...$args), 0);
        add_filter('posts_search', fn(...$args) => $this->jobPostsSearchFilter(...$args), 10, 2);
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
            if (is_singular('lowongan') || strpos($_SERVER['REQUEST_URI'] ?? '', '/lowongan/') !== false) {
                // Check if the post exists in trash (deleted due to age)
                global $wp_query;
                $post_name = $wp_query->query_vars['name'] ?? '';
                if ($post_name) {
                    $trashed_post = get_posts(\WPLokerBJM\QueryBuilders\JobQuery::getTrashedJobByNameArgs($post_name));
                    if (!empty($trashed_post)) {
                        // Only send 410 to bots; redirect humans to home
                        if ($this->botDetection->isBot()) {
                            status_header(410);
                            wp_die('This job posting has been removed.', 'Gone', ['response' => 410]);
                        } else {
                            wp_safe_redirect(home_url('/'), 302);
                            exit;
                        }
                    }
                }
                // If not in trash or no name, but URI has /lowongan/, assume removed, send 410 to bots
                if ($this->botDetection->isBot()) {
                    status_header(410);
                    wp_die('This job posting has been removed.', 'Gone', ['response' => 410]);
                } else {
                    wp_safe_redirect(home_url('/'), 302);
                    exit;
                }
            } else {
                // Other 404s redirect to home
                wp_safe_redirect(home_url('/'), 302);
                exit;
            }
        }
    }

    /**
     * Redirect to home if accessing the lowongan post type archive.
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
     | META TAGS
     ======================================================================*/

    /**
     * Adds noindex and nofollow directives to robots meta tag.
     * - Noindex for lowongan post type archive page.
     * - Noindex,nofollow for staging/dev subdomains.
     */
    public function robotsMeta(array $robots): array
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
    public function restHeaders(): void
    {
        try {
            if (!is_user_logged_in()) {
                return;
            }

            $nonce = wp_create_nonce('wp_rest');
            header('X-WP-Nonce: ' . $nonce);
        } catch (\Exception $e) {
            error_log('Hooks::restHeaders error: ' . $e->getMessage());
        }
    }

    /**
     * Modifies HTTP headers to remove unwanted Link headers and add sitemap link.
     */
    public function modifyLinkHeaders(): void
    {
        if (!headers_sent()) {
            // Remove all Link headers to prevent API discovery exposure
            header_remove('Link');

            $this->exposeSitemapHeader();
        }
    }

    public function exposeSitemapHeader(): void
    {
        $sitemap_url = home_url('/sitemap_index.xml');
        header('Link: <' . esc_url($sitemap_url) . '>; rel="sitemap"');
    }

    /**
     * Exposes specific headers for REST API responses.
     */
    public function filterRestHeaders($served, $result, $request, $server)
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
     * !Customizes the SQL WHERE clause for WordPress search queries on job posts.
     * this used for SearchForm
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

    private function listPluginsToDisable(?array $extra = []): array
    {
        return array_merge([
            'google-site-kit/',
            'seo-by-rank-math/',
            'fast-indexing-api/',
            'wps-hide-login/',
        ], $extra);
    }
}