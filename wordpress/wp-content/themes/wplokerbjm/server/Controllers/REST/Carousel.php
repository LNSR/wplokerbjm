<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Shared\Log\Logger;
class Carousel
{

    public function __construct(
        private \WPLokerBJM\Presenters\Components\JobCarousel $jobCarouselPresenter
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        try {

            $props = $this->jobCarouselPresenter->getProps();

            $response = new \WP_REST_Response([
                'jobs' => $props['jobs'] ?? [],
                'totalJobs' => $props['totalJobs'] ?? 0,
            ]);

            // Set pagination headers
            $response->header('X-WP-Total', $props['totalJobs'] ?? 0);

            // Set Link header for pagination (assuming page 1 for carousel)
            ControllerUtils::setPaginationLinks($response, $request, 1, $props['maxNumPages'] ?? 1, 'carousel', 'paged');


            return rest_ensure_response($response);
        } catch (\Exception $e) {
            Logger::error('REST', 'Carousel::handle error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('Internal server error', 500);
        }
    }
}