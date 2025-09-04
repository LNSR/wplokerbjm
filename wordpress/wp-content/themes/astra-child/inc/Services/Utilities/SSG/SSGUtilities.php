<?php

namespace AstraChild\Services\Utilities\SSG;

/**
 * SSG Utilities
 *
 * Utility class for Static Site Generation file operations
 */
class SSGUtilities
{
    /**
     * Get the file path for the SSG version
     */
    public static function getSSGFilePath(\WP_Post $post): string
    {
        $themeDir = get_stylesheet_directory();
        $postUrl = get_permalink($post);

        // Convert URL to file path (same logic as RedirectToSSG.php)
        $urlParts = parse_url($postUrl);
        $path = $urlParts['path'] ?? '/';

        // Remove trailing slash except for root
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = substr($path, 0, -1);
        }

        // Handle root path
        if ($path === '/' || $path === '') {
            $filePath = 'index.html';
        } else {
            // Add .html extension if not present
            $filePath = $path . (substr($path, -5) === '.html' ? '' : '.html');
        }

        // Remove leading slash
        $filePath = ltrim($filePath, '/');

        return $themeDir . '/assets/ssg/' . $filePath;
    }

    /**
     * Delete the SSG file for a post
     */
    public static function deleteSSGFile(int $post_id, string $reason): void
    {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        // Get the SSG file path
        $ssgFilePath = self::getSSGFilePath($post);

        // Delete the file if it exists
        if (file_exists($ssgFilePath)) {
            if (unlink($ssgFilePath)) {
                error_log("SSG Delete: Successfully deleted SSG file: $ssgFilePath (Reason: $reason)");

                // Purge LiteSpeed cache for this post and SSG tag
                if (function_exists('litespeed_purge_post')) {
                    litespeed_purge_post($post_id);
                }
                if (function_exists('do_action')) {
                    do_action('litespeed_purge_tag', 'ssg');
                }
            } else {
                error_log("SSG Delete: Failed to delete SSG file: $ssgFilePath (Reason: $reason)");
            }
        } else {
            error_log("SSG Delete: SSG file not found: $ssgFilePath (Reason: $reason)");
        }

        // Also clean up empty directories
        self::cleanupEmptyDirectories(dirname($ssgFilePath));
    }

    /**
     * Clean up empty directories recursively
     */
    public static function cleanupEmptyDirectories(string $dirPath): void
    {
        $ssgBaseDir = get_stylesheet_directory() . '/assets/ssg';

        // Don't go above the SSG base directory
        if (strpos($dirPath, $ssgBaseDir) !== 0) {
            return;
        }

        // Recursively clean up empty directories
        while ($dirPath !== $ssgBaseDir && is_dir($dirPath)) {
            $files = array_diff(scandir($dirPath), ['.', '..']);
            if (empty($files)) {
                rmdir($dirPath);
                error_log("SSG Delete: Removed empty directory: $dirPath");
                $dirPath = dirname($dirPath);
            } else {
                break;
            }
        }
    }

    /**
     * Collect paths from a post for SSG generation
     */
    public static function collectPathsFromPost(int $post_id, string $permalink, string $homeUrl): array
    {
        // Convert permalinks to full URLs for SSG generation
        $paths = [];

        if (!empty($permalink)) {
            $paths[] = $permalink;
        }

        // Also include home page so that index may be rebuilt if necessary
        $paths[] = $homeUrl;

        // include any other derived paths if you need (category pages, author, tag, etc.)
        return array_values(array_unique($paths));
    }
}