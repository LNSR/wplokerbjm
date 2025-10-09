<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Services\Utilities\Utilities;

class JobGridController
{
    public function __construct(
        private \WPLokerBJM\Presenters\Components\JobGrid $jobGridPresenter
    ) {
    }

    public function handle(\WP_REST_Request $request)
    {
        try {
            $paged = intval($request->get_param('paged') ?? 1);
            $context = $request->get_param('context') ?? 'latest';
            $title = $request->get_param('title') ?? '';
            $total_jobs = intval($request->get_param('total_jobs') ?? 0);

            if ($paged < 1) {
                return new \WP_Error('invalid_paged', 'Parameter "paged" must be greater than 0.', ['status' => 400]);
            }

            $filters = Utilities::parseJobFilters($request);

            $query_args = match ($context) {
                'search' => JobQuery::searchJobsArgs($filters, $paged, 12),
                'archive' => JobQuery::latestJobsArgs($paged, 12),
                default => JobQuery::latestJobsArgs($paged, 12),
            };

            $props = $this->jobGridPresenter->getProps($query_args, $title, $context, $total_jobs);

            $response = new \WP_REST_Response($props);

            // Set pagination headers if applicable
            if (isset($props['maxNumPages'])) {
                $response->header('X-WP-TotalPages', $props['maxNumPages']);
            }

            return $response;
        } catch (\Exception $e) {
            error_log('JobGrid::handle error: ' . $e->getMessage());
            return new \WP_Error('server_error', 'An error occurred while processing the request.', ['status' => 500]);
        }
    }
}
