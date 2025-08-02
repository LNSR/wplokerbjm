<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Services\Utilities\Utilities;
use AstraChild\Services\REST\RESTData;

class LoadMore
{
    public function __construct(
        private RESTData $restData
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
            $query = match ($context) {
                'search' => new \WP_Query(JobQuery::searchJobsArgs($filters, $paged, 36)),
                default => new \WP_Query(JobQuery::latestJobsArgs($paged, 12)),
            };
        } catch (\Throwable $e) {
            return new \WP_Error('query_error', 'Failed to execute job query.', [
                'status' => 500,
                'details' => $e->getMessage(),
            ]);
        }

        if ($paged > $query->max_num_pages && $query->max_num_pages > 0) {
            return new \WP_Error('exceed_max_pages', 'Parameter "paged" exceeds max_num_pages.', [
                'status' => 400,
                'max_num_pages' => $query->max_num_pages,
            ]);
        }

        $jobs = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $jobs[] = $this->restData->getCardData(get_the_ID());
            }
            wp_reset_postdata();
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
