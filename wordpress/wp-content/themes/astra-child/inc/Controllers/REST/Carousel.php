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
        $args = JobQuery::getCarouselArgs(-1);

        $result = $this->jobRepository->queryCard($args);

        $jobs = $result['jobs'] ?? [];
        $query = $result['query'] ?? new \WP_Query();

        return rest_ensure_response([
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ]);
    }
}