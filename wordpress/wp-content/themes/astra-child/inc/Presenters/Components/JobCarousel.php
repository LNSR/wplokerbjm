<?php

namespace AstraChild\Presenters\Components;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Repositories\JobRepository;
class JobCarousel
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {
    }

    public function getProps(): array
    {

        $args = JobQuery::getCarouselArgs(-1);
        $query = new \WP_Query($args);

        $result = $this->jobRepository->queryCard($args);
        $jobs = $result['jobs'] ?? [];

        $props = [
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ];

        return $props;
    }
}