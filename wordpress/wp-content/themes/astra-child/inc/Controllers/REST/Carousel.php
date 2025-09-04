<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;

class Carousel
{

    public function __construct(
        private \AstraChild\Repositories\JobRepository $jobRepository
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        try {

            $args = JobQuery::getCarouselArgs(-1);

            $result = $this->jobRepository->queryCard($args);

            $jobs = $result['jobs'] ?? [];
            $query = $result['query'] ?? new \WP_Query();

            $response = [
                'jobs' => $jobs,
                'totalJobs' => $query->found_posts,
            ];

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('Carousel::handle error: ' . $e->getMessage());
            return rest_ensure_response(['jobs' => [], 'totalJobs' => 0]);
        }
    }
}