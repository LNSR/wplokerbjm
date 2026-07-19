<?php

namespace WPLokerBJM\Presenters\Components;
use JobGridData;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};

/**
 * @phpstan-import-type CardData from \WPLokerBJM\Services\GraphQL\GraphQLData
 * 
 * @phpstan-type JobGridData array{
 *     jobs: list<CardData>,
 *     maxNumPages: int,
 *     context: 'latest'|'search',
 *     filters: array{cari: string, lokasi: string, gender: string, pendidikan: string, sort: string},
 *     title: string,
 *     totalJobs: int
 * }
 */
class JobGrid
{

    public function __construct(
        private JobRepository $jobRepository
    ) {
    }

    /**
     * Get job grid data with caching.
     *
     * Fetches paginated job listings using the provided WP_Query args and formats
     * them for grid display. Supports search and latest contexts with filtering.
     *
     * @param array<string, mixed> $query_args WP_Query arguments for fetching jobs
     * @param string $title Section title (auto-generated from context if empty)
     * @phpstan-param JobGridData['context'] $context Display context
     * @param int $total_jobs Total jobs count override (0 = auto-detect from query)
     * @return JobGridData
     */
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
                'cari' => (string) ($_GET['cari'] ?? ''),
                'lokasi' => (string) ($_GET['lokasi'] ?? ''),
                'gender' => (string) ($_GET['gender'] ?? ''),
                'pendidikan' => (string) ($_GET['pendidikan'] ?? ''),
                'sort' => (string) ($_GET['sort'] ?? 'desc'),
            ],
            'title' => $title,
            'totalJobs' => $jobs_query->found_posts,
        ];

        Cache::set($cacheKey, $props, 86400); // Cache for 1 day

        return $props;
    }
}