<?php

namespace WPLokerBJM\Controllers\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};

class JobSchemaController
{
    public function __construct(
        private readonly \WPLokerBJM\Services\REST\RESTData $restData
    ) {
    }

    /**
     * Handle REST API request for job schema
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $body = $request->get_json_params();
            $ids_param = $body['post_ids'] ?? null;

            if (empty($ids_param)) {
                return ControllerUtils::failedResponse('post_ids parameter is required', 400);
            } elseif (!is_array($ids_param)) {
                return ControllerUtils::failedResponse('Invalid post_ids parameter.', 400);
            }

            $ids = ControllerUtils::validateIds($ids_param);
            if (empty($ids)) {
                return new WP_REST_Response([], 200);
            } elseif (count($ids) > 1000) {
                return ControllerUtils::failedResponse('Maximum of 1000 post IDs allowed.', 400);
            }

            // Generate deterministic cache key based on sorted post_ids
            sort($ids); // Ensure consistency regardless of input order
            $cacheKey = CacheKey::REST_JOB_SCHEMA_BATCH_PREFIX . md5(implode(',', $ids));
            $cachedResponse = Cache::get($cacheKey);
            if ($cachedResponse !== false) {
                return new WP_REST_Response($cachedResponse, 200);
            }

            $args = \WPLokerBJM\QueryBuilders\JobQuery::allJobsIdsArgs();
            $args['post__in'] = $ids;

            $query = new \WP_Query($args);
            $existing_ids = $query->posts;

            $response = [];
            foreach ($existing_ids as $post_id) {
                $schema = $this->restData->JobSchema($post_id);
                if (!empty($schema)) {
                    $response[] = $schema;
                }
            }

            // Cache the response for 1 day (matching individual schema TTL)
            Cache::set($cacheKey, $response, 86400);

            return new WP_REST_Response($response, 200);
        } catch (\Exception $e) {
            Logger::error('REST', 'JobSchemaController::handle error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('Internal server error', 500);
        }
    }
}