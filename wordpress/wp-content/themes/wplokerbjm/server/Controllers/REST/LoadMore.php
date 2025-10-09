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
            $context = $request->get_param('context') ?? 'archive';

            if ($paged < 1) {
                return new \WP_Error('invalid_paged', 'Parameter "paged" must be greater than 0.', ['status' => 400]);
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
                return new \WP_Error('exceed_max_pages', 'Parameter "paged" exceeds max_num_pages.', [
                    'status' => 400,
                    'max_num_pages' => $query->max_num_pages,
                ]);
            }

            // If no jobs found, you can return a 404 or empty array (optional)
            if (empty($jobs)) {
                return new \WP_Error('no_jobs', 'No jobs found for the given parameters.', ['status' => 404]);
            }

            $response = new \WP_REST_Response([
                'jobs' => $jobs,
                'context' => $context,
                'filters' => $filters,
            ]);

            // Set pagination headers
            $response->header('X-WP-Total', $query->found_posts);
            $response->header('X-WP-TotalPages', $query->max_num_pages);

            // Set Link header for pagination
            Utilities::setPaginationLinks($response, $request, $paged, $query->max_num_pages, 'load-more', 'paged');

            return $response;
        } catch (\Exception $e) {
            error_log('LoadMore::handle error: ' . $e->getMessage());
            return new \WP_Error('server_error', 'An error occurred while processing the request.', ['status' => 500]);
        }
    }

}
