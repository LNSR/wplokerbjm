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
                foreach ($post_ids as $post_id) {
                    $title = get_the_title($post_id);
                    if (!empty($title)) {
                        $results[] = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
            }
        }
        return rest_ensure_response(array_values(array_unique($results)));
    }
}
