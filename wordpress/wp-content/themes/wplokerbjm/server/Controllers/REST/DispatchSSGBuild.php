<?php

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * REST endpoint for manually triggering SSG builds
 */
class DispatchSSGBuild
{
    public function __construct(
        private \WPLokerBJM\Services\Webhooks\TriggerBuildSSG $triggerBuild
    ) {
    }

    /**
     * Handle the REST API request
     */
    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            // Check access permissions
            $accessResponse = $this->checkAccess();
            if ($accessResponse !== null) {
                return $accessResponse;
            }

            // Check rate limiting
            $rateLimitCheck = $this->checkRateLimit();
            if ($rateLimitCheck instanceof \WP_REST_Response) {
                return $rateLimitCheck;
            }
            $cacheKey = $rateLimitCheck;

            // Validate and prepare inputs
            $inputs = $this->validateAndPrepareInputs($request);
            if ($inputs instanceof \WP_REST_Response) {
                return $inputs;
            }
            $paths = $inputs['paths'];
            $reason = $inputs['reason'];
            $dryRun = $inputs['dry_run'];

            // Set rate limit before processing
            Cache::set($cacheKey, time(), 120); // 2 minutes

            // Trigger the build
            $result = $this->triggerBuild->trigger($paths, $reason, $dryRun);

            // Return response
            $statusCode = $result['success'] ? 200 : 500;

            return new \WP_REST_Response([
                'success' => $result['success'],
                'paths' => $paths,
                'reason' => $reason,
                'dry_run' => $dryRun,
                'result' => $result,
            ], $statusCode);
        } catch (\Exception $e) {
            Logger::error('REST', 'DispatchSSGBuild::handle error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('Internal server error', 500);
        }
    }

    /**
     * Check access permissions and environment restrictions
     */
    private function checkAccess(): ?\WP_REST_Response
    {
        // Check if user has permission (admin only)
        if (!current_user_can('manage_options')) {
            return ControllerUtils::failedResponse('Insufficient permissions', 403);
        }

        // Disable API for localhost
        if (SharedUtils::isLocalhost()) {
            return ControllerUtils::failedResponse('SSG API is disabled for localhost', 403);
        }

        return null; // Access granted
    }

    /**
     * Check rate limiting for the current user
     */
    private function checkRateLimit(): \WP_REST_Response|string
    {
        $userId = get_current_user_id();
        $cacheKey = CacheKey::SSG_API_RATE_LIMIT_PREFIX . $userId;
        $lastRequest = Cache::get($cacheKey);

        if ($lastRequest !== false) {
            return ControllerUtils::failedResponse('Rate limit exceeded. Please wait before making another request.', 429);
        }

        return $cacheKey;
    }

    /**
     * Validate and prepare input parameters
     */
    private function validateAndPrepareInputs(\WP_REST_Request $request): \WP_REST_Response|array
    {
        $paths = $request->get_param('paths');
        $reason = $request->get_param('reason') ?: 'manual_trigger';

        // Normalize dry_run: accept "true"/"false", true/false, 1/0
        $rawDry = $request->get_param('dry_run') ?? 'false';
        $dryRun = filter_var($rawDry, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $dryRun = ($dryRun === null) ? false : $dryRun;

        // Validate paths
        if (empty($paths) || !is_array($paths)) {
            return ControllerUtils::failedResponse('Paths parameter is required and must be an array', 400);
        }

        return [
            'paths' => $paths,
            'reason' => $reason,
            'dry_run' => $dryRun,
        ];
    }
}
