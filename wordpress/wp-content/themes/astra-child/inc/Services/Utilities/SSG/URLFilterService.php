<?php

namespace AstraChild\Services\Utilities\SSG;

/**
 * URL Filtering Service for SSG
 *
 * Provides centralized URL filtering logic to avoid code duplication
 */
class URLFilterService
{
    /**
     * Filter out unwanted URLs from the paths array
     *
     * @param array $paths Array of URLs/paths to filter
     * @param string $context Context for logging (e.g., 'API', 'Trigger')
     * @return array Filtered array of paths
     */
    public static function filterPaths(array $paths, string $context = 'SSG'): array
    {
        $filtered = [];

        foreach ($paths as $path) {
            if (self::isPathAllowed($path)) {
                $filtered[] = $path;
            } else {
                error_log("{$context}: Filtered out path: {$path}");
            }
        }

        return $filtered;
    }

    /**
     * Check if a path is allowed for SSG processing
     *
     * @param string $path The URL or path to check
     * @return bool True if allowed, false if should be filtered
     */
    public static function isPathAllowed(string $path): bool
    {
        // Parse the URL to extract query parameters
        $parsedUrl = parse_url($path);

        // If no query string, allow the path
        if (!isset($parsedUrl['query'])) {
            return true;
        }

        // Parse query parameters
        parse_str($parsedUrl['query'], $queryParams);

        // Filter out specific post types
        $blockedPostTypes = self::getBlockedPostTypes();

        if (isset($queryParams['post_type']) && in_array($queryParams['post_type'], $blockedPostTypes, true)) {
            return false;
        }

        // You can add more filtering logic here
        // For example, filter by specific post IDs, query parameters, etc.

        return true;
    }

    /**
     * Get the list of blocked post types
     *
     * @return array Array of blocked post type slugs
     */
    public static function getBlockedPostTypes(): array
    {
        return [
            'od_url_metrics',
            // Add other post types to block here
        ];
    }

    /**
     * Add a post type to the blocked list
     *
     * @param string $postType Post type slug to block
     * @return void
     */
    public static function addBlockedPostType(string $postType): void
    {
        $blockedTypes = self::getBlockedPostTypes();
        if (!in_array($postType, $blockedTypes, true)) {
            $blockedTypes[] = $postType;
            // In a real implementation, you'd want to persist this or use a filter
            // For now, this is just a demonstration
        }
    }
}