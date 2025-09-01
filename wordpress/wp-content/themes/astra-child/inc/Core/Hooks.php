<?php

namespace AstraChild\Core;

use AstraChild\Contracts\HooksInterface;
use AstraChild\Core\Hooks\JQuery;
use AstraChild\Core\Hooks\Theme;
use AstraChild\Core\Hooks\Google;
use AstraChild\Core\Hooks\Litespeed;


class Hooks implements HooksInterface
{
    /*======================================================================
     | REGISTER HOOKS
     ======================================================================*/

    public function registerActions(): void
    {
        add_action('wp_enqueue_scripts', [JQuery::class, 'disableJquery'], 0);
        add_action('wp_head', [JQuery::class, 'suppressJqueryErrors'], 0);
        add_action('wp_head', [Theme::class, 'injectThemeScript'], 3);
        add_action('wp_head', [Google::class, 'injectAdHideInlineStyle'], 3);
        add_action('wp_head', [Theme::class, 'injectNoScriptWarning'], 5);
        add_action('wp_head', [Theme::class, 'injectWpUserLoggedInFlag'], 5); // login status
        add_action('litespeed_purged_all', [Litespeed::class, 'clearObjectCacheAndTransient'], 20);
    }

    /*======================================================================
     | REGISTER FILTERS
     ======================================================================*/

    public function registerFilters(): void
    {
        add_filter('posts_search', [$this, 'jobPostsSearchFilter'], 10, 2);
        add_filter('litespeed_optimize_js_excludes', [Litespeed::class, 'lscJsExcludes'], 0);
        add_filter('litespeed_optimize_css_excludes', [Litespeed::class, 'lscCssExcludes'], 0);
        add_filter('option_active_plugins', [$this, 'disablePluginsForSSG'], 0);
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
            $search = \AstraChild\QueryBuilders\JobQuery::buildPostsSearchSql($wpdb, $q);
        }
        return $search;
    }

    /**
     * Temporarily disable specific plugins if SSG bot is detected.
     */
    public function disablePluginsForSSG($plugins)
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ssgBotUAs = ['SSG-Bot/1.0', 'Mozilla/5.0 (compatible; SSG-Bot/1.0)'];
        $isSSGBot = false;
        foreach ($ssgBotUAs as $ua) {
            if (stripos($userAgent, $ua) !== false) {
                $isSSGBot = true;
                break;
            }
        }
        if ($isSSGBot) {
            $pluginsToDisable = [
                'google-site-kit/',
                'updraftplus/',
                'view-admin-as/',
                'wp-crontrol/',
                'seo-by-rank-math/',
                'fast-indexing-api/',
                'tinymce-advanced/',
                'akismet/',
                'litespeed-cache/',
                'wps-hide-login/',
                'health-check/',
                'duplicate-wp-page-post/',
                'child-theme-configurator/',
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