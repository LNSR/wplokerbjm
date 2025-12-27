<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Services\Utilities\Utilities;
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
                return Utilities::failedResponse('Parameter "paged" must be greater than 0.', 400);
            }

            $filters = Utilities::parseJobFilters($request);

            $args = match ($context) {
                'search' => JobQuery::searchJobsArgs($filters, $paged, 36),
                default => JobQuery::latestJobsArgs($paged, 12),
            };

            $result = $this->jobRepository->queryCard($args);

            $jobs = $result['jobs'] ?? [];
            $query = $result['query'] ?? new \WP_Query();

            if ($paged > $query->max_num_pages && $query->max_num_pages > 0) {
                return Utilities::failedResponse('Parameter "paged" exceeds max_num_pages.', 400);
            }

            // If no jobs found, you can return a 404 or empty array (optional)
            if (empty($jobs)) {
                return Utilities::failedResponse('No jobs found for the given parameters.', 404);
            }

            $response = new \WP_REST_Response(Utilities::filterEmptyValues([
                'jobs' => $jobs,
                'context' => $context,
                'filters' => $filters,
            ]));
            // Set pagination headers
            $response->header('X-WP-Total', $query->found_posts);
            $response->header('X-WP-TotalPages', $query->max_num_pages);

            // Set Link header for pagination
            Utilities::setPaginationLinks($response, $request, $paged, $query->max_num_pages, 'load-more', 'paged');

            return $response;
        } catch (\Exception $e) {
            error_log('LoadMore::handle error: ' . $e->getMessage());
            return Utilities::failedResponse('An error occurred while processing the request.', 500);
        }
    }

}
