<?php

namespace WPLokerBJM\Controllers\REST;
use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};

class AutoSuggestionSearch
{
    public function handle(\WP_REST_Request $request)
    {
        try {
            $query = sanitize_text_field($request->get_param('query'));

            $cacheKey = CacheKey::AUTO_SUGGESTION_PREFIX . md5($query);
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return rest_ensure_response($cached);
            }

            $results = [];

            if ($query && strlen($query) >= 4) {
                $args = \WPLokerBJM\QueryBuilders\JobQuery::autoSuggestionArgs($query);
                $post_ids = get_posts($args);

                if (!empty($post_ids) && !is_wp_error($post_ids)) {
                    $results = array_map(function ($post_id) {
                        return html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }, $post_ids);
                }
            }

            $uniqueResults = array_values(array_unique($results));

            Cache::set($cacheKey, $uniqueResults, 86400); // Cache for 1 day

            return rest_ensure_response($uniqueResults);
        } catch (\Exception $e) {
            Logger::error('REST', 'AutoSuggestionSearch::handle error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('Internal server error', 500);
        }
    }
}
