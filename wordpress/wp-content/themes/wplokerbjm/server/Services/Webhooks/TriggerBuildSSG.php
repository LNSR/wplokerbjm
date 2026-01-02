<?php

namespace WPLokerBJM\Services\Webhooks;

use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Configs\CredentialConfig;
/**
 * TriggerBuild
 *
 * Responsible for notifying GitHub Actions to regenerate SSG assets.
 *
 * Configuration (set in wp-config.php or environment):
 * - SSG_GITHUB_TOKEN (required)
 * - SSG_GITHUB_OWNER (required)
 * - SSG_GITHUB_REPO (required)
 * - SSG_GITHUB_WORKFLOW (required)  // workflow filename or id
 * - SSG_GITHUB_REF (optional, default 'main')
 */
class TriggerBuildSSG
{

    private string $token;
    private string $owner;
    private string $repo;
    private string $workflow;
    private string $ref;


    public function __construct(
        private ValidateSSGCredentials $validator,
        ?array $config = null,
    ) {
        $this->loadCredentials($config);
    }

    /**
     * Load SSG GitHub credentials from configuration
     */
    protected function loadCredentials(?array $config = null): void
    {
        $creds = CredentialConfig::SSGGitHubCredential($config);
        $this->token = $creds['token'];
        $this->owner = $creds['owner'];
        $this->repo = $creds['repo'];
        $this->workflow = $creds['workflow'];
        $this->ref = $creds['ref'];
    }

    /**
     * Validate GitHub credentials and permissions without triggering a workflow run.
     *
     * @return array{
     *   success:bool,
     *   status:int|null,
     *   checks:array{auth:array{success:bool,status:int|null,error:?string},workflow:array{success:bool,status:int|null,error:?string}},
     *   error:?string
     * }
     */
    public function validateCredentials(): array
    {
        return $this->validator->validateCredentials($this->token, $this->owner, $this->repo, $this->workflow);
    }

    /**
     * Check if the token has workflow dispatch permission.
     *
     * This makes a test POST request with invalid inputs to the workflow dispatch endpoint.
     * - 401: No authentication/permission
     * - 403/422: Has permission but request is invalid/forbidden
     *
     * @return array{success:bool, has_permission:bool, status:int|null, error:string|null}
     */
    public function checkDispatchPermission(): array
    {
        return $this->validator->checkDispatchPermission($this->token, $this->owner, $this->repo, $this->workflow);
    }

    /**
     * Trigger a build for a list of site paths.
     *
     * @param string[] $paths  Array of URLs or relative paths (eg "https://site.com/page", "/page")
     * @param string|null $reason optional reason for the dispatch
     * @param bool|null $dryRun optional: if true, request a dry-run in the workflow
     * @return array ['success' => bool, 'status' => int|null, 'body' => string|null, 'error' => string|null]
     */
    public function trigger(array $paths, ?string $reason = null, ?bool $dryRun = null): array
    {
        Logger::info('SSG', "Starting build trigger - Paths: " . json_encode($paths) . ", Reason: " . ($reason ?? 'none') . ", Dry Run: " . ($dryRun ? 'true' : 'false') . ", Workflow: {$this->workflow}");

        if (empty($this->token) || empty($this->owner) || empty($this->repo) || empty($this->workflow)) {
            Logger::error('SSG', "Missing GitHub Actions configuration - Token: " . (!empty($this->token) ? 'set' : 'missing') . ", Owner: {$this->owner}, Repo: {$this->repo}, Workflow: {$this->workflow}");
            return [
                'success' => false,
                'status' => null,
                'body' => null,
                'error' => 'Missing GitHub Actions configuration (token/owner/repo/workflow).',
            ];
        }

        // Check for recent similar requests to prevent double triggers
        $debounceKey = $this->getDebounceKey($paths, $reason, $dryRun);
        $cachedResult = $this->checkDebounceCache($debounceKey);

        if ($cachedResult !== null) {
            Logger::info('SSG', "Skipping duplicate request within debounce window");
            return $cachedResult;
        }

        $endpoint = sprintf(
            'https://api.github.com/repos/%s/%s/actions/workflows/%s/dispatches',
            rawurlencode($this->owner),
            rawurlencode($this->repo),
            rawurlencode($this->workflow)
        );

        $body = [
            'ref' => $this->ref,
            // We pass inputs > workflow should define inputs to accept these
            'inputs' => [
                'paths' => json_encode($paths, JSON_UNESCAPED_SLASHES),
                'reason' => $reason ?? '',
                'dry_run' => ($dryRun === true) ? 'true' : 'false',
            ],
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WPLokerBJM-SSG-Trigger/1.0',
                'Content-Type' => 'application/json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 15,
        ];

        Logger::info('SSG', "Making request to GitHub Actions - Endpoint: $endpoint, Ref: {$this->ref}");

        try {
            $response = wp_remote_post($endpoint, $args);
        } catch (\Exception $e) {
            Logger::error('SSG', "Exception during HTTP request: " . $e->getMessage());
            return [
                'success' => false,
                'status' => null,
                'body' => null,
                'error' => 'Exception during HTTP request: ' . $e->getMessage(),
            ];
        }

        if (is_wp_error($response)) {
            Logger::error('SSG', "HTTP request failed: " . $response->get_error_message());
            $result = [
                'success' => false,
                'status' => null,
                'body' => null,
                'error' => $response->get_error_message(),
            ];
        } else {
            $status = wp_remote_retrieve_response_code($response);
            $resp_body = wp_remote_retrieve_body($response);

            // 204 No Content is expected on success for workflow_dispatch
            $success = ($status >= 200 && $status < 300);

            // Log specific errors for credential issues
            if ($status === 401) {
                Logger::error('SSG', "Authentication failed (401 Unauthorized). Check GitHub token (token is " . (empty($this->token) ? 'empty' : 'set') . ")");
                Logger::debug('SSG', "GitHub API response body: {$resp_body}");
            } elseif ($status === 403) {
                Logger::error('SSG', "Access forbidden (403 Forbidden). Check repository permissions or token scope.");
                Logger::debug('SSG', "GitHub API response body: {$resp_body}");
            } elseif ($status === 404) {
                Logger::error('SSG', "Workflow or repository not found (404 Not Found). Check owner: {$this->owner}, repo: {$this->repo}, workflow: {$this->workflow}");
                Logger::debug('SSG', "GitHub API response body: {$resp_body}");
            }

            Logger::info('SSG', "Request completed - Status: $status, Success: " . ($success ? 'true' : 'false'));

            $result = [
                'success' => $success,
                'status' => $status,
                'body' => $resp_body,
                'error' => $success ? null : 'Non-2xx response from GitHub Actions',
            ];
        }

        // Cache the result for debouncing
        $this->setDebounceCache($debounceKey, $result);

        return $result;
    }

    /**
     * Generate a unique key for debouncing based on request parameters
     */
    private function getDebounceKey(array $paths, ?string $reason, ?bool $dryRun): string
    {
        // Sort paths for consistent key generation
        sort($paths);
        $pathsHash = md5(json_encode($paths));
        $reasonHash = md5($reason ?? '');
        $dryRunStr = $dryRun ? 'true' : 'false';

        return "ssg_trigger_{$pathsHash}_{$reasonHash}_{$dryRunStr}";
    }

    /**
     * Check for recent similar requests to prevent double triggers
     */
    private function checkDebounceCache(string $key): ?array
    {
        try {
            $cached = Cache::get(CacheKey::SSG_DEBOUNCE_PREFIX . $key);

            if ($cached !== false) {
                Logger::debug('SSG', "Found cached result for key: {$key}");
                return $cached;
            }
        } catch (\Exception $e) {
            Logger::error('SSG', "Error checking debounce cache for key: {$key} - " . $e->getMessage());
            return null;
        }

        return null;
    }

    /**
     * Cache the result for debouncing (dynamic timing based on urgency)
     */
    private function setDebounceCache(string $key, array $result): void
    {
        try {
            // Determine debounce timing based on multiple factors
            $debounceSeconds = $this->calculateDynamicDebounceTime($result);

            $cacheKey = CacheKey::SSG_DEBOUNCE_PREFIX . $key;

            Cache::set($cacheKey, $result, $debounceSeconds);

            Logger::debug('SSG', "Set debounce cache for key: {$key} (expiration: {$debounceSeconds}s)");
        } catch (\Exception $e) {
            Logger::error('SSG', "Error setting debounce cache for key: {$key} - " . $e->getMessage());
            return;
        }
    }

    /**
     * Calculate dynamic debounce timing based on various factors
     */
    private function calculateDynamicDebounceTime(array $result): int
    {
        $baseTime = 60; // Base 1 minute

        // Increase time if this was a failed request (retry sooner)
        if (isset($result['success']) && !$result['success']) {
            $baseTime = 30; // 30 seconds for failures
        }

        // Check for high-priority rebuild scenarios
        $highPriorityReasons = ['post_deleted', 'config_changed', 'emergency_rebuild'];
        if (isset($_REQUEST['ssg_reason']) && in_array($_REQUEST['ssg_reason'], $highPriorityReasons)) {
            $baseTime = max(15, $baseTime / 4); // Reduce by 75% for high priority
        }

        // Check for cache bypass requests (reduce debounce)
        if (isset($_GET['ssg_no_cache']) || isset($_GET['ssg_refresh'])) {
            $baseTime = max(10, $baseTime / 6); // Reduce by 83% for cache bypass
        }

        // Environment-based adjustments
        if (defined('WP_ENVIRONMENT_TYPE')) {
            switch (WP_ENVIRONMENT_TYPE) {
                case 'local':
                case 'development':
                    $baseTime = max(5, $baseTime / 12); // Much shorter for dev
                    break;
                case 'staging':
                    $baseTime = max(15, $baseTime / 4); // Shorter for staging
                    break;
                case 'production':
                    $baseTime = min(300, $baseTime * 1.5); // Slightly longer for production
                    break;
            }
        }

        return (int) $baseTime;
    }
}

/**
 * Validate whether SSG credentials are correctly set up.
 */
class ValidateSSGCredentials
{
    /**
     * Validate GitHub credentials and permissions without triggering a workflow run.
     *
     * This performs lightweight GitHub API GET requests:
     * - GET /user to validate token (expired/revoked -> 401)
     * - GET /repos/{owner}/{repo}/actions/workflows/{workflow} to validate repo/workflow access
     *
     * @return array{
     *   success:bool,
     *   status:int|null,
     *   checks:array{auth:array{success:bool,status:int|null,error:?string},workflow:array{success:bool,status:int|null,error:?string}},
     *   error:?string
     * }
     */
    public function validateCredentials(string $token, string $owner, string $repo, string $workflow): array
    {
        Logger::info('SSG', 'Validating GitHub SSG credentials (no dispatch)');

        if (empty($token) || empty($owner) || empty($repo) || empty($workflow)) {
            return [
                'success' => false,
                'status' => null,
                'checks' => [
                    'auth' => ['success' => false, 'status' => null, 'error' => 'Missing token'],
                    'workflow' => ['success' => false, 'status' => null, 'error' => 'Missing owner/repo/workflow'],
                ],
                'error' => 'Missing GitHub Actions configuration (token/owner/repo/workflow).',
            ];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'WPLokerBJM-SSG-Trigger/1.0',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        $authCheck = $this->githubGet('https://api.github.com/user', [
            'headers' => $headers,
            'timeout' => 10,
        ]);

        if (!$authCheck['success']) {
            return [
                'success' => false,
                'status' => $authCheck['status'],
                'checks' => [
                    'auth' => $authCheck,
                    'workflow' => ['success' => false, 'status' => null, 'error' => 'Skipped due to failed auth check'],
                ],
                'error' => $this->humanizeCredentialError($authCheck['status']),
            ];
        }

        $workflowEndpoint = sprintf(
            'https://api.github.com/repos/%s/%s/actions/workflows/%s',
            rawurlencode($owner),
            rawurlencode($repo),
            rawurlencode($workflow)
        );

        $workflowCheck = $this->githubGet($workflowEndpoint, [
            'headers' => $headers,
            'timeout' => 10,
        ]);

        $ok = $authCheck['success'] && $workflowCheck['success'];

        return [
            'success' => $ok,
            'status' => $workflowCheck['status'] ?? $authCheck['status'],
            'checks' => [
                'auth' => $authCheck,
                'workflow' => $workflowCheck,
            ],
            'error' => $ok ? null : $this->humanizeCredentialError($workflowCheck['status']),
        ];
    }

    /**
     * Check if the token has workflow dispatch permission.
     *
     * This makes a test POST request with invalid inputs to the workflow dispatch endpoint.
     * - 401: No authentication/permission
     * - 403/422: Has permission but request is invalid/forbidden
     *
     * @return array{success:bool, has_permission:bool, status:int|null, error:string|null}
     */
    public function checkDispatchPermission(string $token, string $owner, string $repo, string $workflow): array
    {
        Logger::info('SSG', 'Checking GitHub workflow dispatch permission');

        if (empty($token) || empty($owner) || empty($repo) || empty($workflow)) {
            return [
                'success' => false,
                'has_permission' => false,
                'status' => null,
                'error' => 'Missing GitHub Actions configuration (token/owner/repo/workflow).',
            ];
        }

        $endpoint = sprintf(
            'https://api.github.com/repos/%s/%s/actions/workflows/%s/dispatches',
            rawurlencode($owner),
            rawurlencode($repo),
            rawurlencode($workflow)
        );

        // Use invalid inputs to trigger validation error if permission exists
        $body = [
            'ref' => 'invalid-ref-that-does-not-exist',
            'inputs' => [
                'invalid' => 'input',
            ],
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WPLokerBJM-SSG-Trigger/1.0',
                'Content-Type' => 'application/json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 10,
        ];

        try {
            $response = wp_remote_post($endpoint, $args);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'has_permission' => false,
                'status' => null,
                'error' => 'Exception during HTTP request: ' . $e->getMessage(),
            ];
        }

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'has_permission' => false,
                'status' => null,
                'error' => $response->get_error_message(),
            ];
        }

        $status = wp_remote_retrieve_response_code($response);

        // 401 means no permission (unauthorized)
        // 403/422 means has permission but request invalid
        $hasPermission = ($status !== 401);

        Logger::info('SSG', "GitHub dispatch permission check: status={$status}, has_permission=" . ($hasPermission ? 'true' : 'false'));

        return [
            'success' => true,
            'has_permission' => $hasPermission,
            'status' => $status,
            'error' => null,
        ];
    }

    /**
     * Perform a GitHub API GET with standardized error handling.
     *
     * @param string $url
     * @param array $args
     * @return array{success:bool,status:int|null,error:?string}
     */
    private function githubGet(string $url, array $args = []): array
    {
        try {
            $response = wp_remote_get($url, $args);
        } catch (\Exception $e) {
            return ['success' => false, 'status' => null, 'error' => 'Exception during HTTP request: ' . $e->getMessage()];
        }

        if (is_wp_error($response)) {
            return ['success' => false, 'status' => null, 'error' => $response->get_error_message()];
        }

        $status = wp_remote_retrieve_response_code($response);
        $success = ($status >= 200 && $status < 300);
        $body = wp_remote_retrieve_body($response);

        if ($success) {
            Logger::info('SSG', "GitHub GET success: {$url} status={$status}");
        } else {
            Logger::debug('SSG', "GitHub GET failed: {$url} status={$status} body=" . substr((string) $body, 0, 500));
        }

        return [
            'success' => $success,
            'status' => $status,
            'error' => $success ? null : 'Non-2xx response from GitHub API',
        ];
    }

    private function humanizeCredentialError(?int $status): string
    {
        return match ($status) {
            401 => 'GitHub token is invalid/expired/revoked (401 Unauthorized).',
            403 => 'GitHub token is valid but lacks permission (403 Forbidden). Check token scopes/repo access/SSO.',
            404 => 'Repo/workflow not found or not accessible with this token (404 Not Found).',
            default => 'GitHub credential validation failed. Check token, scopes, and repo/workflow settings.',
        };
    }
}