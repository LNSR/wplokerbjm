<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Services\Utilities\Utilities;

class DynamicSearch
{
    public function __construct(
        private \WPLokerBJM\Repositories\JobRepository $jobRepository
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        try {
            $filters = Utilities::parseJobFilters($request);


            $args = \WPLokerBJM\QueryBuilders\JobQuery::searchJobsArgs($filters, 1, 36);

            $result = $this->jobRepository->queryCard($args);

            $jobs = $result['jobs'] ?? [];
            $query = $result['query'] ?? new \WP_Query();

            $response = new \WP_REST_Response([
                'jobs' => $jobs,
                'context' => 'search',
                'filters' => $filters,
            ]);

            // Set pagination headers
            $response->header('X-WP-Total', $query->found_posts);
            $response->header('X-WP-TotalPages', $query->max_num_pages);

            // Set Link header for pagination
            Utilities::setPaginationLinks($response, $request, 1, $query->max_num_pages, 'dynamic-search', 'page');

            return $response;
        } catch (\Exception $e) {
            error_log('DynamicSearch::handle error: ' . $e->getMessage());
            $response = new \WP_REST_Response([
                'jobs' => [],
                'context' => 'search',
                'filters' => []
            ]);
            $response->set_status(500);
            return $response;
        }
    }
}
