<?php

namespace AstraChild\Core;

use AstraChild\Contracts\HooksInterface;
use AstraChild\Core\Hooks\JQuery;
use AstraChild\Core\Hooks\Theme;
use AstraChild\Core\Hooks\Google;


class Hooks implements HooksInterface
{
    /*======================================================================
     | REGISTER HOOKS
     ======================================================================*/

    public function registerActions(): void
    {
        add_action('wp_enqueue_scripts', [JQuery::class, 'disableJquery'], 0);
        add_action('wp_head', [JQuery::class, 'suppressJqueryErrors'], 0);
        add_action('wp_head', [Theme::class, 'injectThemeScript'], 0);
        add_action('wp_head', [Google::class, 'injectAdHideInlineStyle'], 0);
        add_action('wp_head', [Theme::class, 'injectNoScriptWarning'], 0);
        add_action('wp_head', [Theme::class, 'injectWpUserLoggedInFlag'], 0); // login status
        add_action('litespeed_purged_all', [$this, 'deleteCompiledContainer']);
    }

    /*======================================================================
     | REGISTER FILTERS
     ======================================================================*/

    public function registerFilters(): void
    {
        add_filter('posts_search', [$this, 'jobPostsSearchFilter'], 10, 2);
        add_filter('litespeed_optimize_js_excludes', [$this, 'lscJsExcludes'], 2);
        add_filter('litespeed_optimize_css_excludes', [$this, 'lscCssExcludes'], 2);
    }


    /*======================================================================
     | ACTIONS
     ======================================================================*/

    public function deleteCompiledContainer(): void
    {
        $file = get_stylesheet_directory() . '/cache/CompiledContainer.php';
        if (file_exists($file)) {
            unlink($file);
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
        if (!empty($wp_query->query_vars['s'])) {
            $search = '';
            $q = $wp_query->query_vars['s'];
            $q_esc = esc_sql($wpdb->esc_like($q));
            $q_html = esc_sql($wpdb->esc_like(htmlentities($q, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            $search .= " AND (";
            $search .= "{$wpdb->posts}.post_title LIKE '%{$q_esc}%' OR ";
            $search .= "{$wpdb->posts}.post_title LIKE '%{$q_html}%' OR ";
            $search .= "{$wpdb->posts}.ID IN (
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'nama_perusahaan' AND (meta_value LIKE '%{$q_esc}%' OR meta_value LIKE '%{$q_html}%')
        ) OR ";
            $search .= "{$wpdb->posts}.ID IN (
            SELECT object_id FROM {$wpdb->term_relationships}
            INNER JOIN {$wpdb->term_taxonomy} ON {$wpdb->term_taxonomy}.term_taxonomy_id = {$wpdb->term_relationships}.term_taxonomy_id
            INNER JOIN {$wpdb->terms} ON {$wpdb->terms}.term_id = {$wpdb->term_taxonomy}.term_id
            WHERE {$wpdb->term_taxonomy}.taxonomy = 'perusahaan'
            AND {$wpdb->terms}.name LIKE '%{$q_esc}%'
        )";
            $search .= ")";
        }
        return $search;
    }

    /**
     * Exclude specific JS files from LiteSpeed Cache JS optimization.
     */
    public function lscJsExcludes($excludes)
    {
        $excludes[] = '/wp-content/themes/astra-child/assets/';
        $excludes[] = 'main-';
        return $excludes;
    }

    /**
     * Exclude specific CSS files from LiteSpeed Cache CSS optimization.
     */
    public function lscCssExcludes($excludes)
    {
        $excludes[] = '/wp-content/themes/astra-child/assets/';
        $excludes[] = 'main-';
        return $excludes;
    }
}