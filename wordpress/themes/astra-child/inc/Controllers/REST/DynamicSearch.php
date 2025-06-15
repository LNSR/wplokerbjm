<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Resources\Components\JobCard;

class DynamicSearch
{
    public static function handle(\WP_REST_Request $request)
    {
        $filters = [
            'cari' => $request->get_param('cari') ?? '',
            'lokasi' => self::parseMulti($request->get_param('lokasi')),
            'gender' => self::parseMulti($request->get_param('gender')),
            'pendidikan' => self::parseMulti($request->get_param('pendidikan')),
            'sort' => $request->get_param('sort') ?? 'desc',
        ];
        $args = JobQuery::searchJobsArgs($filters, 1, 36);

        $query = new \WP_Query($args);

        $jobs = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $jobs[] = JobCard::getCardData(get_the_ID());
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

    private static function parseMulti($param) {
        if (is_array($param)) return $param;
        if (is_string($param) && strpos($param, ',') !== false) {
            return array_filter(array_map('trim', explode(',', $param)));
        }
        return $param ? [$param] : [];
    }
}
