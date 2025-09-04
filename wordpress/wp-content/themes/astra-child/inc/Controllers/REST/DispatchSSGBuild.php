<?php

namespace AstraChild\Controllers\REST;

use AstraChild\Services\Utilities\SSG\URLFilterService;
use AstraChild\Core\Cache;

/**
 * REST endpoint for manually triggering SSG builds
 */
class DispatchSSGBuild
{
    public function __construct(
        private \AstraChild\Services\PostsManagement\SSG\TriggerBuild $triggerBuild
    ) {
    }

    /**
     * Handle the REST API request
     */
    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            // Check if user has permission (admin only)
            if (!current_user_can('manage_options')) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Insufficient permissions'
                ], 403);
            }

            // Check if request is from localhost
            $islocalhost = [
                '127.0.0.1',
                '::1',
                'localhost',
                '192.168.100.2'
            ];

            // Disable API for localhost
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
            $httpHost = $_SERVER['HTTP_HOST'] ?? '';
            if (in_array($remoteAddr, $islocalhost) || strpos($httpHost, 'localhost') !== false) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'SSG API is disabled for localhost'
                ], 403);
            }

            // Rate limiting: Allow only 1 request per minute per user
            $userId = get_current_user_id();
            $cacheKey = "ssg_api_rate_limit_{$userId}";
            $lastRequest = Cache::get($cacheKey);

            if ($lastRequest !== false) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Rate limit exceeded. Please wait before making another request.',
                    'retry_after' => 60 // seconds
                ], 429);
            }

            $paths = $request->get_param('paths');
            $reason = $request->get_param('reason') ?: 'manual_trigger';

            // Normalize dry_run: accept "true"/"false", true/false, 1/0
            $rawDry = $request->get_param('dry_run') ?? 'false';
            $dryRun = filter_var($rawDry, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $dryRun = ($dryRun === null) ? false : $dryRun;

            // Validate paths
            if (empty($paths) || !is_array($paths)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Paths parameter is required and must be an array'
                ], 400);
            }

            // Filter out unwanted URLs (e.g., od_url_metrics post type)
            $filteredPaths = URLFilterService::filterPaths($paths, 'SSG API');

            // Check if all paths were filtered out
            if (empty($filteredPaths)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'All provided paths were filtered out. No valid paths to process.'
                ], 400);
            }

            // Set rate limit before processing
            Cache::set($cacheKey, time(), 120); // 120 seconds = 2 minutes

            // Trigger the build
            $result = $this->triggerBuild->trigger($filteredPaths, $reason, $dryRun);

            // Return response
            $statusCode = $result['success'] ? 200 : 500;

            return new \WP_REST_Response([
                'success' => $result['success'],
                'paths' => $paths,
                'filtered_paths' => $filteredPaths,
                'reason' => $reason,
                'dry_run' => $dryRun,
                'result' => $result
            ], $statusCode);
        } catch (\Exception $e) {
            error_log('DispatchSSGBuild::handle error: ' . $e->getMessage());
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Return the route args definition for the /dispatch-ssg/ endpoint.
     *
     * @return array
     */
    public function getRouteArgs(): array
    {
        return [
            'paths' => [
                'required' => true,
                'validate_callback' => function ($value) {
                    return is_array($value) && !empty($value);
                },
            ],
            'reason' => [
                'required' => false,
                'default' => 'manual_trigger',
            ],
            'dry_run' => [
                'required' => false,
                'default' => false,
                'validate_callback' => function ($value) {
                    if (is_bool($value)) {
                        return true;
                    }
                    if (is_string($value)) {
                        $v = strtolower($value);
                        return in_array($v, ['true', 'false', '1', '0'], true);
                    }
                    if (is_int($value)) {
                        return in_array($value, [0, 1], true);
                    }
                    return false;
                    ;
                },
            ],
        ];
    }
}
