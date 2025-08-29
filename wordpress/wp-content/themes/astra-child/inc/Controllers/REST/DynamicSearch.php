<?php

namespace AstraChild\Controllers\REST;

use AstraChild\Services\Utilities\Utilities;

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
        $args = \AstraChild\QueryBuilders\JobQuery::searchJobsArgs($filters, 1, 36);

        $result = $this->jobRepository->queryCard($args);

        $jobs = $result['jobs'] ?? [];
        $query = $result['query'] ?? new \WP_Query();

        return rest_ensure_response([
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
            'maxNumPages' => (int) $query->max_num_pages,
            'context' => 'search',
            'filters' => $filters,
        ]);
    }
}
