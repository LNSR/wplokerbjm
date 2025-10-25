<?php

namespace WPLokerBJM\Controllers\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPLokerBJM\Services\Utilities\Utilities;
class SingleOverlay
{
    public function __construct(private \WPLokerBJM\Services\REST\RESTData $restData)
    {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $slug = $request->get_param('slug');
            if (!$slug) {
                return Utilities::failedResponse('Missing slug parameter', 400);
            }

            $post = get_page_by_path($slug, 'OBJECT', 'lowongan');
            if (!$post || !is_object($post)) {
                return Utilities::failedResponse('Post not found', 404);
            }

            $data = $this->restData->getSingleOverlayData($post->ID);

            return new WP_REST_Response($data, 200);
        } catch (\Exception $e) {
            error_log('SingleOverlay::handle error: ' . $e->getMessage());
            return Utilities::failedResponse('Internal server error', 500);
        }
    }
}