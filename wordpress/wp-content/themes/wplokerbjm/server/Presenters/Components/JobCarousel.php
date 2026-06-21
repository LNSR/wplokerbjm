<?php

namespace WPLokerBJM\Presenters\Components;

use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
class JobCarousel
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {
    }

    /**
     * Get carousel jobs data with caching.
     *
     * Fetches carousel job listings using WP_Query args from JobQuery::getCarouselArgs
     * and formats them through JobRepository::queryJob.
     *
     * @return array{jobs: array<int, array>, totalJobs: int} Formatted carousel jobs data
     */
    public function getProps(): array
    {
        $cacheKey = CacheKey::CAROUSEL_JOBS;
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $args = JobQuery::getCarouselArgs(-1);
        $query = new \WP_Query($args);

        $result = $this->jobRepository->queryJob($args);
        $jobs = $result['jobs'] ?? [];

        $props = [
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ];

        Cache::set($cacheKey, $props, 86400); // Cache for 1 day

        return $props;
    }
}