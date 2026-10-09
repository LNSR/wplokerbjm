<?php

namespace WPLokerBJM\Services\SEO;

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\Attributes\Injectable;
use WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\Rankmath;

/**
 * Resolves Rank Math head HTML for internal URLs.
 *
 * Owns URL validation, preview detection, per-URL caching, and the Rank Math
 * headless REST call so the GraphQL transport layer stays thin.
 */
class RankMathHeadService
{
    /**
     * Resolve the Rank Math head HTML for a given URL.
     *
     * Validates that the URL is internal to this site before fetching. Preview
     * requests bypass the cache so the latest draft head is always returned.
     *
     * @param string $url Absolute URL to resolve head HTML for
     * @return string HTML head tags from Rank Math
     * @throws \Exception When the URL is invalid or not internal, or the REST call fails
     */
    public function getHead(string $url): string
    {
        $parsedUrl = parse_url($url);
        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        $postId = $queryParams['p'] ?? $queryParams['preview_id'] ?? $queryParams['id'] ?? null;

        $isPreview = !empty($postId) || isset($queryParams['preview']);

        // Validate URL - must be internal
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Logger::error('GraphQL', 'Invalid URL format: ' . $url);
            throw new \Exception('Invalid URL format');
        }

        $home_url = \home_url();
        if (strpos($url, $home_url) !== 0) {
            Logger::error('GraphQL', 'URL not internal. Home URL: ' . $home_url . ', Provided URL: ' . $url);
            throw new \Exception('URL must be internal to this site');
        }

        $cacheKey = null;
        if (!$isPreview) {
            $cacheKey = CacheKey::RANKMATH_HEAD_PREFIX . md5($url);
            /** @var string|false $cached */
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $data = $this->fetchHeadlessData($url);

        $html = $data['head'] ?? '';
        if (!$isPreview && Rankmath::isActive()) {
            Cache::set($cacheKey, $html, 86400); // Cache for 1 day
        }

        return $html;
    }

    /**
     * Fetch head data from Rank Math's headless REST endpoint.
     *
     * @param string $url Internal URL to fetch head data for
     * @return array<string, mixed> Decoded REST response payload
     * @throws \Exception When Rank Math returns a WP_Error or a failure payload
     */
    private function fetchHeadlessData(string $url): array
    {
        if (!Rankmath::isActive()) {
            return [];
        }

        $headless = new \RankMath\Rest\Headless();
        $request = new \WP_REST_Request('GET', '/rankmath/v1/getHead');
        $request->set_param('url', $url);
        $response = $headless->get_head($request);

        if (is_wp_error($response)) {
            Logger::error('GraphQL', 'REST handler error: ' . $response->get_error_message());
            throw new \Exception('Failed to fetch head data');
        }

        $data = $response->get_data();

        if (!isset($data['success']) || !$data['success']) {
            Logger::error('GraphQL', 'REST handler returned failure');
            throw new \Exception('REST handler failed');
        }

        return $data;
    }
}
