<?php

namespace WPLokerBJM\Controllers\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Shared\Log\Logger;
class JobDetail
{
    public function __construct(private \WPLokerBJM\Presenters\Pages\SinglePresenter $singlePresenter)
    {
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $slug = $request->get_param('slug');
            if (!$slug) {
                return ControllerUtils::failedResponse('Missing slug parameter', 400);
            }

            $post = get_page_by_path($slug, 'OBJECT', 'lowongan');
            if (!$post || !is_object($post)) {
                return ControllerUtils::failedResponse('Post not found', 404);
            }

            $data = $this->singlePresenter->getProps($post->ID)['job'];

            return new WP_REST_Response($data, 200);
        } catch (\Exception $e) {
            Logger::error('REST', 'SingleOverlay::handle error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('Internal server error', 500);
        }
    }
}