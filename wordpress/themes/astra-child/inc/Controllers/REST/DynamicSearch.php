<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Services\Utilities\Utilities;
use AstraChild\Services\REST\RESTData;

class DynamicSearch
{
    public function __construct(
        private RESTData $restData
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
        $args = JobQuery::searchJobsArgs($filters, 1, 36);

        $query = new \WP_Query($args);

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
            'context' => 'search',
            'filters' => $filters,
        ]);
    }
}
