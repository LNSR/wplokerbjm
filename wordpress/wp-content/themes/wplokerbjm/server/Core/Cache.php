<?php
namespace WPLokerBJM\Core;
/**
 * Transient Cache management class for wplokerbjm theme.
 *
 * Note: With LiteSpeed Cache's "Store Transients" enabled, transients are redirected to Redis object cache
 * for improved performance, instead of database storage. This applies to set(), get(), delete(), and deletePattern() methods.
 */
class Cache
{
    const TRANSIENT_PREFIX = 'wplokerbjm_';
    /**
     * Set a transient value.
     *
     * @param string $key The transient key.
     * @param mixed $value The value to store.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return bool True if the value was set, false otherwise.
     */
    public static function set($key, $value, $expiration = 0)
    {
        try {
            $transient_key = self::TRANSIENT_PREFIX . $key;
            $result = set_transient($transient_key, $value, $expiration);
            return $result;
        } catch (\Exception $e) {
            error_log('Cache::set error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a transient value.
     *
     * @param string $key The transient key.
     * @return mixed The transient value or false if not found.
     */
    public static function get($key): mixed
    {
        try {
            $transient_key = self::TRANSIENT_PREFIX . $key;
            $value = get_transient($transient_key);
            return $value;
        } catch (\Exception $e) {
            error_log('Cache::get error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a transient.
     *
     * @param string $key The transient key.
     * @return bool True if the transient was deleted, false otherwise.
     */
    public static function delete($key): bool
    {
        try {
            $transient_key = self::TRANSIENT_PREFIX . $key;
            $result = delete_transient($transient_key);
            error_log('Transient key deleted: ' . $transient_key);
            return $result;
        } catch (\Exception $e) {
            error_log('Cache::delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete transients matching a pattern.
     * * Note: Use delete() for exact keys (e.g., 'carousel_jobs_api_').
     * * Use deletePattern() for wildcard patterns (e.g., 'auto_suggestion_%') to clear multiple caches.
     * * deletePattern() performs a database query and is slower for large datasets.
     * * When LiteSpeed Cache redirects transients to Redis, also deletes from Redis.
     * @param string $pattern The pattern to match (e.g., 'auto_suggestion_%').
     * @return int Number of transients deleted.
     */
    public static function deletePattern($pattern): int
    {
        try {
            $result = \WPLokerBJM\QueryBuilders\DBQuery\CacheQuery::deletePatternQuery($pattern);
            error_log('Pattern deleted: ' . $pattern . ', total deleted: ' . $result);
            return $result;
        } catch (\Exception $e) {
            error_log('Cache::deletePattern error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Increment a cache value using transients (redirected to Redis).
     *
     * This method uses WordPress transients for increments, which are redirected to Redis object cache
     * when LiteSpeed Cache's "Store Transients" is enabled. This provides atomic-like increments
     * with Redis performance for counters.
     *
     * Used as a fallback by ObjectCache::increment() when object cache is unavailable or fails.
     *
     * @param string $key The cache key (will be prefixed with TRANSIENT_PREFIX).
     * @param int $value The value to increment by. Default 1.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return int|false The new incremented value, or false if increment failed.
     */
    public static function incrementTransient($key, $value = 1, $expiration = 0): int|false
    {
        // fallback to transient increment
        try {
            $current = (int) self::get($key) ?: 0;
            $new_value = $current + $value;
            self::set($key, $new_value, $expiration);
            return $new_value;
        } catch (\Exception $e) {
            error_log('Cache increment fallback failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrement a cache value using transients (redirected to Redis).
     *
     * This method uses WordPress transients for decrements, which are redirected to Redis object cache
     * when LiteSpeed Cache's "Store Transients" is enabled. This provides atomic-like decrements
     * with Redis performance for counters.
     *
     * Used as a fallback by ObjectCache::decrement() when object cache is unavailable or fails.
     *
     * @param string $key The cache key (will be prefixed with TRANSIENT_PREFIX).
     * @param int $value The value to decrement by. Default 1.
     * @return int|false The new decremented value, or false if decrement failed.
     */
    public static function decrementTransient($key, $value = 1): int|false
    {
        // fallback to transient decrement
        try {
            $current = (int) self::get($key) ?: 0;
            $new_value = $current - $value;
            self::set($key, $new_value, 0); // No expiration for decrement
            return $new_value;
        } catch (\Exception $e) {
            error_log('Cache decrement fallback failed: ' . $e->getMessage());
            return false;
        }
    }
}