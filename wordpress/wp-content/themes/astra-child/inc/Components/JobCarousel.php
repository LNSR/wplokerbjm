<?php

namespace AstraChild\Components;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Repositories\JobRepository;
use AstraChild\Services\REST\RESTData;

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

        return [
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ];
    }
}