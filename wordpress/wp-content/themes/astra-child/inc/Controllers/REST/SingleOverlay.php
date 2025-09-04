<?php

namespace AstraChild\Controllers\REST;

use WP_REST_Request;
use WP_REST_Response;
use AstraChild\Core\Cache;

class SingleOverlay
{
    public function __construct(private \AstraChild\Services\REST\RESTData $restData)
    {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $slug = $request->get_param('slug');
            if (!$slug) {
                return new WP_REST_Response(['error' => 'Missing slug parameter'], 400);
            }

            $cacheKey = 'single_overlay_api_' . sanitize_key($slug);

            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return new WP_REST_Response($cached, 200);
            }

            $post = get_page_by_path($slug, 'OBJECT', 'lowongan');
            if (!$post || !is_object($post)) {
                return new WP_REST_Response(['error' => 'Post not found'], 404);
            }

            $data = $this->restData->getSingleOverlayData($post->ID);

            Cache::set($cacheKey, $data, 86400); // Cache for 24 hours

            return new WP_REST_Response($data, 200);
        } catch (\Exception $e) {
            error_log('SingleOverlay::handle error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => 'Internal server error'], 500);
        }
    }
}