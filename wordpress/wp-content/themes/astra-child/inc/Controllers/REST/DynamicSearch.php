<?php

namespace AstraChild\Controllers\REST;

use AstraChild\Services\Utilities\Utilities;

class DynamicSearch
{
    public function __construct(
        private \AstraChild\Repositories\JobRepository $jobRepository
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        try {
            $filters = [
                'cari' => $request->get_param('cari') ?? '',
                'lokasi' => Utilities::parseMulti($request->get_param('lokasi')),
                'gender' => Utilities::parseMulti($request->get_param('gender')),
                'pendidikan' => Utilities::parseMulti($request->get_param('pendidikan')),
                'sort' => $request->get_param('sort') ?? 'desc',
            ];


            $args = \AstraChild\QueryBuilders\JobQuery::searchJobsArgs($filters, 1, 36);

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
