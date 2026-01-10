<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
class LoadMore
{
    public function __construct(
        private \WPLokerBJM\Repositories\JobRepository $jobRepository
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        try {
            $paged = intval($request->get_param('paged') ?? 1);
            $context = $request->get_param('context') ?? 'latest';

            if ($paged < 1) {
                return ControllerUtils::failedResponse('Parameter "paged" must be greater than 0.', 400);
            }

            $filters = ControllerUtils::parseJobFilters($request);

            $cacheKey = CacheKey::LOAD_MORE_PREFIX . md5(serialize([$paged, $context, $filters]));
            $cached = Cache::get($cacheKey);

            if ($cached !== false) {
                $response = new \WP_REST_Response($cached['data']);
                $response->header('X-WP-Total', $cached['total']);
                $response->header('X-WP-TotalPages', $cached['totalPages']);
                ControllerUtils::setPaginationLinks($response, $request, $paged, $cached['totalPages'], 'load-more', 'paged');
                return $response;
            }

            $args = match ($context) {
                'search' => JobQuery::searchJobsArgs($filters, $paged, 27),
                default => JobQuery::latestJobsArgs($paged, 27),
            };

            $result = $this->jobRepository->queryJob($args);

            $jobs = $result['jobs'] ?? [];
            $query = $result['query'] ?? new \WP_Query();

            if ($paged > $query->max_num_pages && $query->max_num_pages > 0) {
                return ControllerUtils::failedResponse('Parameter "paged" exceeds max_num_pages.', 400);
            }

            // If no jobs found, you can return a 404 or empty array (optional)
            if (empty($jobs)) {
                return ControllerUtils::failedResponse('No jobs found for the given parameters.', 404);
            }

            $data = SharedUtils::filterEmptyValues([
                'jobs' => $jobs,
                'context' => $context,
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
            ControllerUtils::setPaginationLinks($response, $request, $paged, $query->max_num_pages, 'load-more', 'paged');

            return $response;
        } catch (\Exception $e) {
            Logger::error('REST', 'LoadMore::handle error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('An error occurred while processing the request.', 500);
        }
    }

}
