<?php

namespace AstraChild\QueryBuilders\DBQuery;
use AstraChild\Core\Cache;
/**
 * Cache query builder for database operations.
 */
class CacheQuery
{

    /**
     * Delete transients matching a pattern.
     * * Note: Use delete() for exact keys (e.g., 'carousel_jobs_api_').
     * * Use deletePattern() for wildcard patterns (e.g., 'auto_suggestion_%') to clear multiple caches.
     * * deletePattern() performs a database query and is slower for large datasets.
     * @param string $pattern The pattern to match (e.g., 'auto_suggestion_%').
     * @return int Number of transients deleted.
     */
    public static function deletePatternQuery($pattern): int
    {
        global $wpdb;

        $like = '%' . $wpdb->esc_like(Cache::TRANSIENT_PREFIX . $pattern) . '%';
        $timeout_like = '%' . $wpdb->esc_like('_transient_timeout_' . Cache::TRANSIENT_PREFIX . $pattern) . '%';

        $option_table = $wpdb->options;

        // Delete transient values
        $result1 = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$option_table} WHERE option_name LIKE %s",
            $like
        ));

        // Delete timeout entries
        $result2 = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$option_table} WHERE option_name LIKE %s",
            $timeout_like
        ));

        $result1 = ($result1 === false ? 0 : $result1);
        $result2 = ($result2 === false ? 0 : $result2);

        return $result1 + $result2;
    }
}
