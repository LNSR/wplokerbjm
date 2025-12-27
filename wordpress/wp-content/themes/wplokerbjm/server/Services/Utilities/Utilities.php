<?php
namespace WPLokerBJM\Services\Utilities;
use WPLokerBJM\Services\REST\RESTRoute;
use WPLokerBJM\Models\Schema\Taxonomies;
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
            Taxonomies::LOKASI_PEKERJAAN => self::parseMulti($request->get_param(Taxonomies::LOKASI_PEKERJAAN)),
            Taxonomies::GENDER => self::parseMulti($request->get_param(Taxonomies::GENDER)),
            Taxonomies::PENDIDIKAN => self::parseMulti($request->get_param(Taxonomies::PENDIDIKAN)),
            'sort' => $request->get_param('sort') ?? 'desc',
        ];
    }

    public static function isLocalhost(): bool
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $httpHost = $_SERVER['HTTP_HOST'] ?? '';
        $serverName = $_SERVER['SERVER_NAME'] ?? '';

        // Check exact localhost addresses
        $exactLocalhost = [
            '127.0.0.1',
            '::1',
            'localhost',
        ];

        if (in_array($remoteAddr, $exactLocalhost)) {
            return true;
        }

        // Check for localhost in host/server name
        if (strpos($httpHost, 'localhost') !== false || strpos($serverName, 'localhost') !== false) {
            return true;
        }

        // Check for private network ranges (development environments)
        $privateRanges = ['192.168.', '10.0.', '172.'];

        foreach ($privateRanges as $range) {
            if (strpos($remoteAddr, $range) !== false || strpos($httpHost, $range) !== false || strpos($serverName, $range) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function failedResponse(string $message, int $code = 400): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'error' => $message,
        ], $code);
    }

    public static function isDevelopment(): bool
    {
        return defined('WP_ENV') && WP_ENV === 'development';
    }

    /**
     * Recursively filter out empty values from an array.
     *! make arrays returned values more compact by removing empty entries 
     *
     * @param array $dataArray The input array to filter.
     * @param bool|null $disable Optional flag to disable filtering in development environment.
     * @return array The filtered array with empty values removed.
     */
    public static function filterEmptyValues(array $dataArray, ?bool $disable = false): array
    {
        // In development environment, return data as is for easier debugging
        if (self::isDevelopment() && $disable) {
            return $dataArray;
        }

        $filtered = [];
        foreach ($dataArray as $key => $value) {
            if (is_array($value)) {
                $filteredValue = self::filterEmptyValues($value);
                if (!empty($filteredValue)) {
                    $filtered[$key] = $filteredValue;
                }
            } elseif ($value !== null && $value !== '' && $value !== []) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}