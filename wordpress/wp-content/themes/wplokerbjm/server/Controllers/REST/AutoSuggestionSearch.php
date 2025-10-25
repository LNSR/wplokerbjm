<?php

namespace WPLokerBJM\Controllers\REST;
use WPLokerBJM\Services\Utilities\Utilities;

class AutoSuggestionSearch
{
    public function handle(\WP_REST_Request $request)
    {
        try {
            $query = sanitize_text_field($request->get_param('query'));

            $results = [];

            if ($query && strlen($query) >= 2) {
                $args = \WPLokerBJM\QueryBuilders\JobQuery::autoSuggestionArgs($query);
                $post_ids = get_posts($args);

                if (!empty($post_ids) && !is_wp_error($post_ids)) {
                    $results = array_map(function ($post_id) {
                        return html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }, $post_ids);
                }
            }

            $uniqueResults = array_values(array_unique($results));

            return rest_ensure_response($uniqueResults);
        } catch (\Exception $e) {
            error_log('AutoSuggestionSearch::handle error: ' . $e->getMessage());
            return Utilities::failedResponse('Internal server error', 500);
        }
    }
}
