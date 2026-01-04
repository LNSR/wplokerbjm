<?php

namespace WPLokerBJM\Presenters\Components;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};

class JobGrid
{

    public function __construct(
        private JobRepository $jobRepository
    ) {
    }

    public function getProps(array $query_args, string $title, string $context = 'latest', int $total_jobs = 0): array
    {
        $cacheKey = CacheKey::JOB_GRID_PREFIX . md5(serialize($query_args));
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $result = $this->jobRepository->queryJob($query_args);

        $jobs = $result['jobs'] ?? [];
        $jobs_query = $result['query'] ?? new \WP_Query();

        if (!$title) {
            $title = match ($context) {
                'search' => 'Hasil Pencarian',
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
            'totalJobs' => $jobs_query->found_posts,
        ];

        Cache::set($cacheKey, $props, 3600);

        return $props;
    }
}