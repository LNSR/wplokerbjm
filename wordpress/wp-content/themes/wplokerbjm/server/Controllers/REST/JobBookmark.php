<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\QueryBuilders\JobQuery;
use WP_REST_Request;
use WP_REST_Response;

class JobBookmark
{
    public function __construct(
        private readonly \WPLokerBJM\Services\REST\RESTData $restData
    ) {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $ids_param = $request->get_param('ids');

        if (empty($ids_param)) {
            return new WP_REST_Response([], 200);
        }

        // Parse comma-separated IDs
        $ids = array_map('intval', explode(',', $ids_param));
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return new WP_REST_Response([], 200);
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
