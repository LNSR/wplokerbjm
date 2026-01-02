<?php
namespace WPLokerBJM\Controllers\Utilities;
use WPLokerBJM\Services\REST\RESTRoute;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
class ControllerUtils
{
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
        $parseMulti = function ($param) {
            if (is_array($param))
                return $param;
            if (is_string($param) && strpos($param, ',') !== false) {
                return array_filter(array_map('trim', explode(',', $param)));
            }
            return $param ? [$param] : [];
        };

        return [
            'cari' => $request->get_param('cari') ?? '',
            Taxonomies::LOKASI_PEKERJAAN => $parseMulti($request->get_param(Taxonomies::LOKASI_PEKERJAAN)),
            Taxonomies::GENDER => $parseMulti($request->get_param(Taxonomies::GENDER)),
            Taxonomies::PENDIDIKAN => $parseMulti($request->get_param(Taxonomies::PENDIDIKAN)),
            'sort' => $request->get_param('sort') ?? 'desc',
        ];
    }

    public static function failedResponse(string $message, int $code = 400): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'error' => $message,
        ], $code);
    }

    public static function buildTermsTree(array $terms, $taxonomy = ''): array
    {
        try {

            $terms_by_id = [];
            foreach ($terms as $term) {
                $terms_by_id[$term->term_id] = [
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'parent' => $term->parent,
                    'children' => [],
                ];
            }
            $tree = [];
            foreach ($terms_by_id as &$term) {
                if ($term['parent'] && isset($terms_by_id[$term['parent']])) {
                    $terms_by_id[$term['parent']]['children'][] = &$term;
                } else {
                    $tree[] = &$term;
                }
            }
            unset($term);

            return $tree;
        } catch (\Exception $e) {
            Logger::error('Taxonomy', 'TaxonomyService::buildTermsTree error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check and enforce rate limiting for API endpoints
     *
     * @param string $cacheKeyPrefix The cache key prefix for rate limiting
     * @param int $limit Maximum requests allowed in the time window
     * @param int $windowSeconds Time window in seconds
     * @param string|null $identifier Optional custom identifier (defaults to client IP)
     * @return \WP_REST_Response|null Returns error response if rate limit exceeded, null if allowed
     */
    public static function checkRateLimit(string $cacheKeyPrefix, int $limit = 20, int $windowSeconds = 60, ?string $identifier = null): ?\WP_REST_Response
    {
        $clientIP = $identifier ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $rateLimitKey = $cacheKeyPrefix . md5($clientIP);
        $currentCount = Cache::get($rateLimitKey) ?: 0;

        if ($currentCount >= $limit) {
            return self::failedResponse('Rate limit exceeded. Please wait before making another request.', 429);
        }

        Cache::set($rateLimitKey, $currentCount + 1, $windowSeconds);
        return null;
    }
}