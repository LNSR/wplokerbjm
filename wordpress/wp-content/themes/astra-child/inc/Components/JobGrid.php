<?php

namespace AstraChild\Components;
use AstraChild\Repositories\JobRepository;
use AstraChild\Services\Job\JobServices;
use AstraChild\Services\REST\RESTData;

class JobGrid
{

    public function __construct(
        private JobServices $jobServices,
        private RESTData $restData,
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

        return $this->getVueProps($jobs, $jobs_query, $context, $title, $total_jobs);
    }

    public function getSchemaCard(array $query_args): array
    {
        $result = $this->jobRepository->queryCard($query_args);
        return $result['schema'] ?? [];
    }
}