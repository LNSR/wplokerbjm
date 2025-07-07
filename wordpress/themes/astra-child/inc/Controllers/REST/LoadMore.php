<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Resources\Components\JobCard;
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

        $filters = [
            'cari' => $request->get_param('cari') ?? '',
            'lokasi' => Utilities::parseMulti($request->get_param('lokasi')),
            'gender' => Utilities::parseMulti($request->get_param('gender')),
            'pendidikan' => Utilities::parseMulti($request->get_param('pendidikan')),
            'sort' => $request->get_param('sort') ?? 'desc',
        ];

        $query = match ($context) {
            'search' => new \WP_Query(JobQuery::searchJobsArgs($filters, $paged, 36)),
            default => new \WP_Query(JobQuery::latestJobsArgs($paged, 12)),
        };

        $jobs = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $jobs[] = $this->restData->getCardData(get_the_ID());
            }
            wp_reset_postdata();
        }
        return rest_ensure_response([
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
            'maxNumPages' => (int) $query->max_num_pages,
            'context' => $context,
            'filters' => $filters,
        ]);
    }
}
