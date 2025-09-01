<?php

namespace AstraChild\Controllers\REST;

use AstraChild\Core\Cache;

class AutoSuggestionSearch
{
    public function handle(\WP_REST_Request $request)
    {
        $query = sanitize_text_field($request->get_param('query'));
        $cacheKey = 'auto_suggestion_' . sanitize_key($query);

        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $results = [];

        if ($query && strlen($query) >= 2) {
            $args = \AstraChild\QueryBuilders\JobQuery::autoSuggestionArgs($query);
            $post_ids = get_posts($args);

            if (!empty($post_ids) && !is_wp_error($post_ids)) {
                $results = array_map(function ($post_id) {
                    return html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }, $post_ids);
            }
        }

        $uniqueResults = array_values(array_unique($results));

        Cache::set($cacheKey, $uniqueResults, 86400); // Cache for 24 hours

        return rest_ensure_response($uniqueResults);
    }
}
