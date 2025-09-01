<?php

namespace AstraChild\Controllers\REST;

use AstraChild\Services\Utilities\Utilities;
use AstraChild\Core\Cache;

class DynamicSearch
{
    public function __construct(
        private \AstraChild\Repositories\JobRepository $jobRepository
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        $filters = [
            'cari' => $request->get_param('cari') ?? '',
            'lokasi' => Utilities::parseMulti($request->get_param('lokasi')),
            'gender' => Utilities::parseMulti($request->get_param('gender')),
            'pendidikan' => Utilities::parseMulti($request->get_param('pendidikan')),
            'sort' => $request->get_param('sort') ?? 'desc',
        ];

        $cacheKey = 'dynamic_search_' . sanitize_key($filters['cari']) . '_' . implode('_', array_map('sanitize_key', $filters['lokasi'])) . '_' . implode('_', array_map('sanitize_key', $filters['gender'])) . '_' . implode('_', array_map('sanitize_key', $filters['pendidikan'])) . '_' . sanitize_key($filters['sort']);

        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $args = \AstraChild\QueryBuilders\JobQuery::searchJobsArgs($filters, 1, 36);

        $result = $this->jobRepository->queryCard($args);

        $jobs = $result['jobs'] ?? [];
        $query = $result['query'] ?? new \WP_Query();

        $response = [
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
            'maxNumPages' => (int) $query->max_num_pages,
            'context' => 'search',
            'filters' => $filters,
        ];

        Cache::set($cacheKey, $response, 86400); // Cache for 24 hours

        return rest_ensure_response($response);
    }
}
