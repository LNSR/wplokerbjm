<?php

namespace AstraChild\Controllers\REST;

use WP_REST_Request;
use WP_REST_Response;
use AstraChild\Services\REST\RESTData;

class SingleOverlay
{
    public function __construct(private RESTData $restData)
    {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        if (!$slug) {
            return new WP_REST_Response(['error' => 'Missing slug parameter'], 400);
        }

        $post = get_page_by_path($slug, OBJECT, 'lowongan');
        if (!$post) {
            return new WP_REST_Response(['error' => 'Post not found'], 404);
        }

        $data = $this->restData->getSingleOverlayData($post->ID);
        return new WP_REST_Response($data, 200);
    }
}