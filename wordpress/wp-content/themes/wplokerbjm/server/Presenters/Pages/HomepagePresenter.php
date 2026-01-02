<?php

namespace WPLokerBJM\Presenters\Pages;

use WPLokerBJM\Presenters\Components\{JobGrid, JobCarousel};
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Presenters\Schema\JobPostingSchema;

class HomepagePresenter
{
    public function __construct(
        private JobGrid $jobGrid,
        private JobCarousel $jobCarousel,
        private JobRepository $jobRepository
    ) {
    }

    public function getHomepageData(): array
    {
        $query_args = JobQuery::latestJobsArgs(1, 12);
        $query_result = $this->jobRepository->queryJob($query_args);
        $schema_data = $query_result['schema_data'];

        $props = [
            'carousel' => $this->jobCarousel->getProps(),
            'jobGrid' => $this->jobGrid->getProps($query_args, 'Lowongan Terbaru', 'latest'),
        ];

        $post_ids = array_map(fn($job) => $job['id'] ?? null, $query_result['jobs']) ?? null;
        $schema = JobPostingSchema::renderMultiple($schema_data, $post_ids);

        return [
            'props' => $props,
            'schema' => $schema,
        ];
    }
}