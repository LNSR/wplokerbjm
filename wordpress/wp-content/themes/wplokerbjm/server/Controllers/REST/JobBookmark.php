<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WP_REST_Request;
use WP_REST_Response;

class JobBookmark
{
    public function __construct(
        private readonly \WPLokerBJM\Services\REST\RESTData $restData,
    ) {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        $ids_param = $body['ids'] ?? null;

        if (empty($ids_param)) {
            return new WP_REST_Response([], 200);
        } elseif (!is_array($ids_param)) {
            return ControllerUtils::failedResponse('Invalid IDs parameter.', 400);
        }

        $ids = ControllerUtils::validateIds($ids_param);
        if (empty($ids)) {
            return new WP_REST_Response([], 200);
        } elseif (count($ids) > 10000) {
            return ControllerUtils::failedResponse('Maximum of 10000 IDs allowed.', 400);
        }

        $args = JobQuery::allJobsIdsArgs();
        $args['post__in'] = $ids;

        $query = new \WP_Query($args);
        $existing_ids = $query->posts;

        $response = [];
        foreach ($existing_ids as $post_id) {
            $response[] = $this->restData->getCardData($post_id);
        }

        return new WP_REST_Response($response, 200);
    }
}
