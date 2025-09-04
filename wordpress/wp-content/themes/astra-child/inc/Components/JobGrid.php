<?php

namespace AstraChild\Components;
use AstraChild\Repositories\JobRepository;
use AstraChild\Services\Job\JobServices;
use AstraChild\Core\Cache;

class JobGrid
{

    public function __construct(
        private JobServices $jobServices,
        private JobRepository $jobRepository
    ) {
    }


    /**
     * Build Vue props array for hydration.
     */
    protected static function getVueProps(array $jobs, \WP_Query $jobs_query, string $context, string $title, int $total_jobs): array
    {
        return [
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
            'totalJobs' => $total_jobs
        ];
    }

    public function getProps(array $query_args, string $title, string $context = 'latest', int $total_jobs = 0): array
    {
        $cache_key = 'component_job_grid_' . md5(serialize($query_args) . $title . $context . $total_jobs);
        $cached = Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

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

        $props = $this->getVueProps($jobs, $jobs_query, $context, $title, $total_jobs);

        Cache::set($cache_key, $props, 86400); // Cache for 1 day
        return $props;
    }

    public function getSchemaCard(array $query_args): array
    {
        $result = $this->jobRepository->queryCard($query_args);
        return $result['schema'] ?? [];
    }
}