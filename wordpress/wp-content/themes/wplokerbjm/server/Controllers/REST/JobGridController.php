<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Shared\Log\Logger;

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
                return ControllerUtils::failedResponse('Parameter "paged" must be a positive integer.', 400);
            }

            $filters = ControllerUtils::parseJobFilters($request);

            $query_args = match ($context) {
                'search' => JobQuery::searchJobsArgs($filters, $paged, 27),
                default => JobQuery::latestJobsArgs($paged, 27),
            };

            $props = $this->jobGridPresenter->getProps($query_args, $title, $context, $total_jobs);

            $response = new \WP_REST_Response($props);

            // Set pagination headers if applicable
            if (isset($props['maxNumPages'])) {
                $response->header('X-WP-TotalPages', $props['maxNumPages']);
            }
            if (isset($props['totalJobs'])) {
                $response->header('X-WP-Total', $props['totalJobs']);
            }

            return $response;
        } catch (\Exception $e) {
            Logger::error('REST', 'JobGrid::handle error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('An error occurred while processing the request.', 500);
        }
    }
}
