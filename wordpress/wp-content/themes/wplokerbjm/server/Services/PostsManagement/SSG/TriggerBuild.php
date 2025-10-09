<?php

namespace WPLokerBJM\Services\PostsManagement\SSG;

use WPLokerBJM\Core\Cache;
use WPLokerBJM\Services\Utilities\SSG\URLFilterService;

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
class TriggerBuild
{
    private string $token;
    private string $owner;
    private string $repo;
    private string $workflow;
    private string $ref;

    public function __construct(
        array $config = []
    ) {
        // Prefer explicit config, fall back to constants
        $this->token = $config['token'] ?? (defined('SSG_GITHUB_TOKEN') ? SSG_GITHUB_TOKEN : '');
        $this->owner = $config['owner'] ?? (defined('SSG_GITHUB_OWNER') ? SSG_GITHUB_OWNER : '');
        $this->repo = $config['repo'] ?? (defined('SSG_GITHUB_REPO') ? SSG_GITHUB_REPO : '');
        $this->workflow = $config['workflow'] ?? (defined('SSG_GITHUB_WORKFLOW') ? SSG_GITHUB_WORKFLOW : '');
        $this->ref = $config['ref'] ?? (defined('SSG_GITHUB_REF') ? SSG_GITHUB_REF : '');
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
        error_log("SSG Trigger: Starting build trigger - Paths: " . json_encode($paths) . ", Reason: " . ($reason ?? 'none') . ", Dry Run: " . ($dryRun ? 'true' : 'false') . ", Workflow: {$this->workflow}");

        if (empty($this->token) || empty($this->owner) || empty($this->repo) || empty($this->workflow)) {
            error_log("SSG Trigger: ERROR - Missing GitHub Actions configuration - Token: " . (!empty($this->token) ? 'set' : 'missing') . ", Owner: {$this->owner}, Repo: {$this->repo}, Workflow: {$this->workflow}");
            return [
                'success' => false,
                'status' => null,
                'body' => null,
                'error' => 'Missing GitHub Actions configuration (token/owner/repo/workflow).',
            ];
        }

        // Normalize paths: ensure full URLs and unique
        $normalized = array_values(array_unique(array_map(function ($p) {
            $p = trim((string) $p);
            if ($p === '') {
                return home_url('/');
            }
            // If it's already a full URL, return as-is
            if (filter_var($p, FILTER_VALIDATE_URL)) {
                return $p;
            }
            // Convert relative path to full URL
            return home_url($p);
        }, $paths)));

        // Filter out unwanted paths
        $filtered = URLFilterService::filterPaths($normalized, 'SSG Trigger');

        error_log("SSG Trigger: Normalized paths: " . json_encode($normalized));
        error_log("SSG Trigger: Filtered paths: " . json_encode($filtered));

        // Check for recent similar requests to prevent double triggers
        $debounceKey = $this->getDebounceKey($filtered, $reason, $dryRun);
        $cachedResult = $this->checkDebounceCache($debounceKey);

        if ($cachedResult !== null) {
            error_log("SSG Trigger: Skipping duplicate request within debounce window");
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
                'paths' => json_encode($filtered, JSON_UNESCAPED_SLASHES),
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

        error_log("SSG Trigger: Making request to GitHub Actions - Endpoint: $endpoint, Ref: {$this->ref}");

        try {
            $response = wp_remote_post($endpoint, $args);
        } catch (\Exception $e) {
            error_log("SSG Trigger: ERROR - Exception during HTTP request: " . $e->getMessage());
            return [
                'success' => false,
                'status' => null,
                'body' => null,
                'error' => 'Exception during HTTP request: ' . $e->getMessage(),
            ];
        }

        if (is_wp_error($response)) {
            error_log("SSG Trigger: ERROR - HTTP request failed: " . $response->get_error_message());
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
                error_log("SSG Trigger: ERROR - Authentication failed (401 Unauthorized). Check GitHub token (token is " . (empty($this->token) ? 'empty' : 'set') . ")");
                error_log("SSG Trigger: GitHub API response body: {$resp_body}");
            } elseif ($status === 403) {
                error_log("SSG Trigger: ERROR - Access forbidden (403 Forbidden). Check repository permissions or token scope.");
                error_log("SSG Trigger: GitHub API response body: {$resp_body}");
            } elseif ($status === 404) {
                error_log("SSG Trigger: ERROR - Workflow or repository not found (404 Not Found). Check owner: {$this->owner}, repo: {$this->repo}, workflow: {$this->workflow}");
                error_log("SSG Trigger: GitHub API response body: {$resp_body}");
            }

            error_log("SSG Trigger: Request completed - Status: $status, Success: " . ($success ? 'true' : 'false'));

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
            $cached = Cache::get("ssg_debounce_{$key}");

            if ($cached !== false) {
                error_log("SSG Trigger: Found cached result for key: {$key}");
                return $cached;
            }
        } catch (\Exception $e) {
            error_log("SSG Trigger: ERROR checking debounce cache for key: {$key} - " . $e->getMessage());
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

            $cacheKey = "ssg_debounce_{$key}";

            Cache::set($cacheKey, $result, $debounceSeconds);

            error_log("SSG Trigger: Set debounce cache for key: {$key} (expiration: {$debounceSeconds}s)");
        } catch (\Exception $e) {
            error_log("SSG Trigger: ERROR setting debounce cache for key: {$key} - " . $e->getMessage());
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

        // Use longer debounce for LiteSpeed-coordinated operations
        if ($this->isLiteSpeedCoordinatedOperation()) {
            $baseTime = 120; // 2 minutes for coordination
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

    /**
     * Check if this is a LiteSpeed-coordinated operation
     */
    private function isLiteSpeedCoordinatedOperation(): bool
    {
        try {
            // Check for LiteSpeed coordination markers
            if (
                isset($_REQUEST['litespeed_ssg_coord']) ||
                (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'LiteSpeed-SSG') !== false)
            ) {
                return true;
            }

            // Check for recent LiteSpeed purge actions using transients
            $purgeTransient = Cache::get('litespeed_recent_purge');
            if ($purgeTransient !== false) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            error_log('TriggerBuild::isLiteSpeedCoordinatedOperation error: ' . $e->getMessage());
            return false;
        }
    }
}
