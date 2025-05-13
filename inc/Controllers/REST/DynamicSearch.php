<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Resources\Components\JobGrid;

class DynamicSearch
{
    public static function handle(\WP_REST_Request $request)
    {
        $filters = [
            'cari' => $request->get_param('cari') ?? '',
            'lokasi' => $request->get_param('lokasi') ?? '',
            'gender' => $request->get_param('gender') ?? '',
            'pendidikan' => $request->get_param('pendidikan') ?? '',
            'sort' => $request->get_param('sort') ?? 'desc',
        ];
        $args = JobQuery::searchJobsArgs($filters, 1, 9);

        $query = new \WP_Query($args);

        $html = JobGrid::render(
            $args,
            'Lowongan Terbaru',
            'search',
            $query->found_posts
        );

        return rest_ensure_response(['html' => $html]);
    }
}
