<?php

namespace AstraChild\Components;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Repositories\JobRepository;
use AstraChild\Core\Cache;

class JobCarousel
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {
    }

    public function getProps(): array
    {
        $cache_key = 'component_job_carousel_props';
        $cached = Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $args = JobQuery::getCarouselArgs(-1);
        $query = new \WP_Query($args);

        $result = $this->jobRepository->queryCard($args);
        $jobs = $result['jobs'] ?? [];

        $props = [
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ];

        Cache::set($cache_key, $props, 86400); // Cache for 1 day
        return $props;
    }
}