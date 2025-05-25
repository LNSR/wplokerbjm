<?php

namespace AstraChild\Controllers\REST;


class AutoSuggestionSearch
{
    public static function handle(\WP_REST_Request $request)
    {
        $query = sanitize_text_field($request->get_param('query'));
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
        return rest_ensure_response(array_values(array_unique($results)));
    }
}
