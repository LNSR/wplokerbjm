<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
class DynamicSearch
{
    public function __construct(
        private \WPLokerBJM\Repositories\JobRepository $jobRepository
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        try {
            $filters = ControllerUtils::parseJobFilters($request);

            $cacheKey = CacheKey::DYNAMIC_SEARCH_PREFIX . md5(serialize($filters));
            $cached = Cache::get($cacheKey);

            if ($cached !== false) {
                $response = new \WP_REST_Response($cached['data']);
                $response->header('X-WP-Total', $cached['total']);
                $response->header('X-WP-TotalPages', $cached['totalPages']);
                ControllerUtils::setPaginationLinks($response, $request, 1, $cached['totalPages'], 'dynamic-search', 'page');
                return $response;
            }

            $args = \WPLokerBJM\QueryBuilders\JobQuery::searchJobsArgs($filters, 1, 36);

            $result = $this->jobRepository->queryJob($args);

            $jobs = $result['jobs'] ?? [];
            $query = $result['query'] ?? new \WP_Query();

            $data = SharedUtils::filterEmptyValues([
                'jobs' => $jobs,
                'context' => 'search',
                'filters' => $filters,
            ]);

            $cacheData = [
                'data' => $data,
                'total' => $query->found_posts,
                'totalPages' => $query->max_num_pages,
            ];

            Cache::set($cacheKey, $cacheData, 3600); // Cache for 1 hour

            $response = new \WP_REST_Response($data);
            
            // Set pagination headers
            $response->header('X-WP-Total', $query->found_posts);
            $response->header('X-WP-TotalPages', $query->max_num_pages);

            // Set Link header for pagination
            ControllerUtils::setPaginationLinks($response, $request, 1, $query->max_num_pages, 'dynamic-search', 'page');

            return $response;
        } catch (\Exception $e) {
            Logger::error('REST', 'DynamicSearch::handle error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('An error occurred while processing the request.', 500);
        }
    }
}
