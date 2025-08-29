<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Services\Utilities\Utilities;

class LoadMore
{
    public function __construct(
    private \AstraChild\Repositories\JobRepository $jobRepository
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        $paged = intval($request->get_param('paged') ?? 1);
        $context = $request->get_param('context') ?? 'archive';

        if ($paged < 1) {
            return new \WP_Error('invalid_paged', 'Parameter "paged" must be greater than 0.', ['status' => 400]);
        }

        $filters = [
            'cari' => $request->get_param('cari') ?? '',
            'lokasi' => Utilities::parseMulti($request->get_param('lokasi')),
            'gender' => Utilities::parseMulti($request->get_param('gender')),
            'pendidikan' => Utilities::parseMulti($request->get_param('pendidikan')),
            'sort' => $request->get_param('sort') ?? 'desc',
        ];

        try {
            $args = match ($context) {
                'search' => JobQuery::searchJobsArgs($filters, $paged, 36),
                default => JobQuery::latestJobsArgs($paged, 12),
            };

            $result = $this->jobRepository->queryCard($args);
        } catch (\Throwable $e) {
            return new \WP_Error('query_error', 'Failed to execute job query.', [
                'status' => 500,
                'details' => $e->getMessage(),
            ]);
        }

        $jobs = $result['jobs'] ?? [];
        $query = $result['query'] ?? new \WP_Query();

        if ($paged > $query->max_num_pages && $query->max_num_pages > 0) {
            return new \WP_Error('exceed_max_pages', 'Parameter "paged" exceeds max_num_pages.', [
                'status' => 400,
                'max_num_pages' => $query->max_num_pages,
            ]);
        }

        // If no jobs found, you can return a 404 or empty array (optional)
        if (empty($jobs)) {
            return new \WP_Error('no_jobs', 'No jobs found for the given parameters.', ['status' => 404]);
        }

        return rest_ensure_response([
            'jobs' => $jobs,
            'pagination' => [
                'current' => $paged,
                'max' => (int) $query->max_num_pages,
            ],
            'context' => $context,
            'filters' => $filters,
        ]);
    }
}
