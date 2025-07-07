<?php

namespace AstraChild\Controllers\REST;

use WP_REST_Request;
use WP_REST_Response;
use AstraChild\Services\REST\RESTData;

class SingleOverlay
{
    protected RESTData $restData;

    public function __construct(RESTData $restData)
    {
        $this->restData = $restData;
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');

        $data = $this->restData->getSingleOverlayData($post_id);
        return new WP_REST_Response($data, 200);
    }
}