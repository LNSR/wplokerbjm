<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Core\Cache;

class Carousel
{

    public function __construct(
        private \AstraChild\Repositories\JobRepository $jobRepository
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        try {
            $cacheKey = 'carousel_jobs_api_';
            $cached = Cache::get($cacheKey);

            if ($cached !== false) {
                return rest_ensure_response($cached);
            }

            $args = JobQuery::getCarouselArgs(-1);

            $result = $this->jobRepository->queryCard($args);

            $jobs = $result['jobs'] ?? [];
            $query = $result['query'] ?? new \WP_Query();

            $response = [
                'jobs' => $jobs,
                'totalJobs' => $query->found_posts,
            ];

            Cache::set($cacheKey, $response, 86400); // Cache for 24 hours

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('Carousel::handle error: ' . $e->getMessage());
            return rest_ensure_response(['jobs' => [], 'totalJobs' => 0]);
        }
    }
}