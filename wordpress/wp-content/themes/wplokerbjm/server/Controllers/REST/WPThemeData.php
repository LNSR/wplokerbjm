<?php

namespace WPLokerBJM\Controllers\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPLokerBJM\Services\Utilities\Utilities;

class WPThemeData
{
    public function __construct(
        private readonly \WPLokerBJM\Services\REST\RESTData $restData
    ) {}

    /**
     * Handle REST API request for theme data
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $themeData = $this->restData->getThemeData();

            if (empty($themeData)) {
                return Utilities::failedResponse('Theme data not available', 404);
            }

            return new WP_REST_Response($themeData, 200);
        } catch (\Exception $e) {
            error_log('WPThemeData::handle error: ' . $e->getMessage());
            return Utilities::failedResponse('Internal server error', 500);
        }
    }
}