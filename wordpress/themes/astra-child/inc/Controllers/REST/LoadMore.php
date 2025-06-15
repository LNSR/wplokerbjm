<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Resources\Components\JobCard;

class LoadMore
{
    public static function handle(\WP_REST_Request $request)
    {
        $paged = intval($request->get_param('paged') ?? 1);
        $context = $request->get_param('context') ?? 'archive';

        $filters = [
            'cari' => $request->get_param('cari') ?? '',
            'lokasi' => self::parseMulti($request->get_param('lokasi')),
            'gender' => self::parseMulti($request->get_param('gender')),
            'pendidikan' => self::parseMulti($request->get_param('pendidikan')),
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
                $jobs[] = JobCard::getCardData(get_the_ID());
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

    private static function parseMulti($param) {
        if (is_array($param)) return $param;
        if (is_string($param) && strpos($param, ',') !== false) {
            return array_filter(array_map('trim', explode(',', $param)));
        }
        return $param ? [$param] : [];
    }
}
