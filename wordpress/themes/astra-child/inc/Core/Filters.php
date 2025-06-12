<?php

namespace AstraChild\Core;

class Filters
{

    /**
     * Register the filter.
     */
    public function register(): void
    {
        add_filter('script_loader_tag', [$this, 'addScriptAttributes'], 10, 2);
        add_filter('posts_search', [$this, 'jobPostsSearchFilter'], 10, 2);

        add_filter('litespeed_optimize_js_excludes', [$this, 'lscJsExcludes']);
        add_filter('litespeed_optimize_css_excludes', [$this, 'lscCssExcludes']);
    }

    /**
     * Add async or defer attribute to specific scripts.
     * Use async for fast, non-blocking scripts and defer for dependencies.
     */
    public function addScriptAttributes(string $tag, string $handle): string
    {
        // Handles for defer
        $deferHandles = [
            'alpinejs',
            'swiper',
            'jquery',
            'select2'
        ];

        // Handles for async - Remove time-post since it depends on Alpine
        $asyncHandles = [
            'dynamic-search',
            'auto-suggestion-search',
            'loadmore-jobs',
            'carousel-swiper',
            'select2-init',
        ];

        if (in_array($handle, $deferHandles, true)) {
            return str_replace(' src', ' defer src', $tag);
        }

        if (in_array($handle, $asyncHandles, true)) {
            return str_replace(' src', ' async src', $tag);
        }

        return $tag;
    }

    /**
     * Customizes the SQL WHERE clause for WordPress search queries on job posts.
     *
     * This filter enables the search form to match posts by:
     *   - Post title
     *   - Post content
     *   - The custom field 'nama_perusahaan'
     *
     * When a search term is present (`$wp_query->query_vars['s']`), this method
     * rewrites the default search SQL to include results where the search term
     * appears in the post title, content, or the 'nama_perusahaan' meta field.
     *
     * @param string   $search   The existing search SQL for WHERE clause.
     * @param \WP_Query $wp_query The current WP_Query instance.
     * @return string  Modified search SQL for WHERE clause.
     *
     * @example
     *  Registered via add_filter('posts_search', [$this, 'jobPostsSearchFilter'], 10, 2);
     *  Used automatically by WP_Query when 's' is set in query args.
     * 
     * TODO: Subject to migrate from custom field to taxonomy for efficient query.
     * 
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
        return $excludes;
    }

    /**
     * Exclude specific CSS files from LiteSpeed Cache CSS optimization.
     */
    public function lscCssExcludes($excludes)
    {
        $excludes[] = '/wp-content/themes/astra-child/assets/css/';
        return $excludes;
    }
}
