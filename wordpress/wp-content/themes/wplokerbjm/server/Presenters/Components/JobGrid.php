<?php

namespace WPLokerBJM\Presenters\Components;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Services\Job\JobSchemaOrg;

class JobGrid
{

    public function __construct(
        private JobSchemaOrg $jobServices,
        private JobRepository $jobRepository
    ) {
    }

    public function getProps(array $query_args, string $title, string $context = 'latest', int $total_jobs = 0): array
    {

        $result = $this->jobRepository->queryCard($query_args);

        $jobs = $result['jobs'] ?? [];
        $jobs_query = $result['query'] ?? new \WP_Query();

        if (!$title) {
            $title = match ($context) {
                'search' => 'Hasil Pencarian',
                'archive' => 'Semua Lowongan',
                'latest' => 'Lowongan Terbaru',
                default => '',
            };
        }

        $props = [
            'jobs' => $jobs,
            'maxNumPages' => (int) $jobs_query->max_num_pages,
            'context' => $context,
            'filters' => [
                'cari' => $_GET['cari'] ?? '',
                'lokasi' => $_GET['lokasi'] ?? '',
                'gender' => $_GET['gender'] ?? '',
                'pendidikan' => $_GET['pendidikan'] ?? '',
                'sort' => $_GET['sort'] ?? 'desc',
            ],
            'title' => $title,
            'totalJobs' => $jobs_query->found_posts
        ];

        return $props;
    }
}