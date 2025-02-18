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

        $query = match ($context) {
            'search' => new \WP_Query(JobQuery::searchJobsArgs([
                'cari' => $request->get_param('cari') ?? '',
                'lokasi' => $request->get_param('lokasi') ?? '',
                'gender' => $request->get_param('gender') ?? '',
                'pendidikan' => $request->get_param('pendidikan') ?? '',
                'sort' => $request->get_param('sort') ?? 'desc',
            ], $paged, 9)),
            default => new \WP_Query(JobQuery::latestJobsArgs($paged, 9)),
        };

        $html = '';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $html .= JobCard::render(get_the_ID(), 'featured');
            }
            wp_reset_postdata();
        }
        return rest_ensure_response(['html' => $html]);
    }
}
