<?php
namespace WPLokerBJM\Services\Utilities;
use WPLokerBJM\Services\REST\RESTRoute;
class Utilities
{
    public static function parseMulti($param)
    {
        if (is_array($param))
            return $param;
        if (is_string($param) && strpos($param, ',') !== false) {
            return array_filter(array_map('trim', explode(',', $param)));
        }
        return $param ? [$param] : [];
    }

    public static function setPaginationLinks(\WP_REST_Response $response, \WP_REST_Request $request, int $current_page, int $total_pages, string $endpoint, string $page_param): void
    {
        if ($total_pages <= 1) {
            return;
        }
        $base_url = home_url('/wp-json/' . RESTRoute::$baseURI . '/' . $endpoint);
        $params = $request->get_query_params();
        $links = [];
        if ($current_page > 1) {
            $first_params = $params;
            $first_params[$page_param] = 1;
            $first_url = add_query_arg($first_params, $base_url);
            $links[] = "<$first_url>; rel=\"first\"";
            $prev_params = $params;
            $prev_params[$page_param] = $current_page - 1;
            $prev_url = add_query_arg($prev_params, $base_url);
            $links[] = "<$prev_url>; rel=\"prev\"";
        }
        if ($current_page < $total_pages) {
            $next_params = $params;
            $next_params[$page_param] = $current_page + 1;
            $next_url = add_query_arg($next_params, $base_url);
            $links[] = "<$next_url>; rel=\"next\"";
            $last_params = $params;
            $last_params[$page_param] = $total_pages;
            $last_url = add_query_arg($last_params, $base_url);
            $links[] = "<$last_url>; rel=\"last\"";
        }
        if (!empty($links)) {
            $response->header('Link', implode(', ', $links));
        }
    }

    public static function parseJobFilters(\WP_REST_Request $request): array
    {
        return [
            'cari' => $request->get_param('cari') ?? '',
            'lokasi' => self::parseMulti($request->get_param('lokasi')),
            'gender' => self::parseMulti($request->get_param('gender')),
            'pendidikan' => self::parseMulti($request->get_param('pendidikan')),
            'sort' => $request->get_param('sort') ?? 'desc',
        ];
    }
}