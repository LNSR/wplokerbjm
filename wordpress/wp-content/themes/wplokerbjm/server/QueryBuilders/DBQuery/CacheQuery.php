<?php

namespace WPLokerBJM\QueryBuilders\DBQuery;
use WPLokerBJM\Core\TransientCache;
/**
 * Cache Query Builder for database operations with LiteSpeed Cache compatibility.
 *
 * This class provides methods for bulk cache operations that work correctly
 * with LiteSpeed Cache's Redis redirection. When LiteSpeed Cache redirects
 * transients to Redis, standard database queries may not clear Redis entries.
 * This class ensures proper cleanup of both database and Redis caches.
 *
 * @package WPLokerBJM\QueryBuilders\DBQuery
 */
class CacheQuery
{

    /**
     * Delete transients matching a pattern using WordPress transient functions.
     * 
     * ! N+1 query(delete) pattern - may be inefficient for large numbers of keys
     * 
     * This method queries the database to find matching transient keys, then uses
     * TransientCache::delete() to properly delete each transient. This ensures compatibility
     * with LiteSpeed Cache's Redis redirection, where transients are stored in Redis
     * instead of the database.
     * 
     * @param string $pattern The pattern to match (e.g., 'auto_suggestion_%').
     *                        Should include wildcards (%) for partial matching.
     * @return int Number of transients successfully deleted.
     *
     * @throws \Exception If database query or transient deletion fails.
     *
     * @note This method performs a database query to discover transient keys,
     *       then uses WordPress transient functions for actual deletion.
     *       For exact key deletion, use TransientCache::delete() directly.
     *       Compatible with LiteSpeed Cache Redis redirection.
     */
    public static function deletePatternQuery($pattern): int
    {
        global $wpdb;

        try {
            $full_pattern = TransientCache::TRANSIENT_PREFIX . $pattern;
            $full_timeout_pattern = '_transient_timeout_' . TransientCache::TRANSIENT_PREFIX . $pattern;

            // Escape the patterns for safety, then restore % as wildcard (assuming % is intentional in controlled patterns)
            $escaped_pattern = str_replace('\\%', '%', $wpdb->esc_like($full_pattern));
            $escaped_timeout_pattern = str_replace('\\%', '%', $wpdb->esc_like($full_timeout_pattern));

            // Prefix % creates "contains" search instead of "starts with"
            // Required for LiteSpeed Cache compatibility - transients may be stored
            // with additional internal prefixes that exact matching would miss
            $like = '%' . $escaped_pattern;
            $timeout_like = '%' . $escaped_timeout_pattern;

            $option_table = $wpdb->options;

            // Get all matching transient keys first
            $transient_keys = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$option_table} WHERE option_name LIKE %s",
                $like
            ));

            $deleted_count = 0;

            // Delete each transient using TransientCache::delete() (works with LiteSpeed Redis)
            foreach ($transient_keys as $key) {
                // Remove _transient_ and prefix to get the original key
                $transient_name = str_replace('_transient_' . TransientCache::TRANSIENT_PREFIX, '', $key);
                if (TransientCache::delete($transient_name)) {
                    $deleted_count++;
                }
            }

            // Clean up timeout entries from database (metadata, not actual transients)
            $result2 = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$option_table} WHERE option_name LIKE %s",
                $timeout_like
            ));

            $result2 = ($result2 === false ? 0 : $result2);

            error_log('Pattern deleted: ' . $pattern . ', total deleted: ' . $deleted_count);
            return $deleted_count;
        } catch (\Exception $e) {
            error_log('CacheQuery::deletePatternQuery error: ' . $e->getMessage());
            return 0;
        }
    }
}
