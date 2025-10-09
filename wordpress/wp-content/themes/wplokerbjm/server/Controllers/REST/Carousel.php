<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Services\Utilities\Utilities;
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
            Utilities::setPaginationLinks($response, $request, 1, $props['maxNumPages'] ?? 1, 'carousel', 'paged');


            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('Carousel::handle error: ' . $e->getMessage());
            return rest_ensure_response(['jobs' => [], 'totalJobs' => 0]);
        }
    }
}