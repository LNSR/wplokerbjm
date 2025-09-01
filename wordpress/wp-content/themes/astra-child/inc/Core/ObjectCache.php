<?php
namespace AstraChild\Core;
use AstraChild\Core\Cache;

/**
 * Object Cache management
 *
 * This class provides direct access to WordPress object cache (e.g., Redis) for high-performance operations.
 * Use this for atomic operations like increments, or when you need fine-grained control over cache groups.
 */
class ObjectCache
{
    const OBJECT_CACHE_PREFIX = 'astra_child_obj_';

    /**
     * Set a value in object cache.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to store.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return bool True if the value was set, false otherwise.
     */
    public static function set($key, $value, $expiration = 0): bool
    {
        if (!function_exists('wp_cache_set')) {
            return false;
        }

        return wp_cache_set($key, $value, self::OBJECT_CACHE_PREFIX, $expiration);
    }

    /**
     * Get a value from object cache.
     *
     * @param string $key The cache key.
     * @return mixed The cached value or false if not found.
     */
    public static function get($key): mixed
    {
        if (!function_exists('wp_cache_get')) {
            return false;
        }

        return wp_cache_get($key, self::OBJECT_CACHE_PREFIX);
    }

    /**
     * Delete a value from object cache.
     *
     * @param string $key The cache key.
     * @return bool True if the value was deleted, false otherwise.
     */
    public static function delete($key): bool
    {
        if (!function_exists('wp_cache_delete')) {
            return false;
        }

        return wp_cache_delete($key, self::OBJECT_CACHE_PREFIX);
    }

    /**
     * Increment a cache value atomically.
     *
     * @param string $key The cache key.
     * @param int $value The value to increment by. Default 1.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return int|false The new incremented value, or false if increment failed.
     */
    public static function increment($key, $value = 1, $expiration = 0): int|false
    {
        if (!function_exists('wp_cache_incr')) {
            return Cache::incrementTransient($key, $value, $expiration);
        }

        $current = self::get($key);
        if ($current === false) {
            // Key doesn't exist, initialize it to 0
            self::set($key, 0, $expiration);
        }

        $result = wp_cache_incr($key, $value, self::OBJECT_CACHE_PREFIX);
        if ($result !== false) {
            if ($expiration > 0) {
                self::set($key . '_expires', time() + $expiration, 0);
            }
            return $result;
        } else {
            error_log('Object cache increment failed (returned false)');
            return Cache::incrementTransient($key, $value, $expiration);
        }
    }

    /**
     * Decrement a cache value atomically.
     *
     * @param string $key The cache key.
     * @param int $value The value to decrement by. Default 1.
     * @return int|false The new decremented value, or false if decrement failed.
     */
    public static function decrement($key, $value = 1): int|false
    {
        if (!function_exists('wp_cache_decr')) {
            return false;
        }

        // Check if key exists first, initialize to 0 if not
        $current = self::get($key);
        if ($current === false) {
            // Key doesn't exist, initialize it to 0
            self::set($key, 0, 0);
        }

        try {
            return wp_cache_decr($key, $value, self::OBJECT_CACHE_PREFIX);
        } catch (\Exception $e) {
            error_log('Object cache decrement failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Flush the entire object cache.
     *
     * Note: This flushes all object cache data, not just the entries in this class's group,
     * as WordPress does not support flushing specific cache groups.
     *
     * @return bool True if the cache was flushed, false otherwise.
     */
    public static function flush(): bool
    {
        if (!function_exists('wp_cache_flush')) {
            return false;
        }

        return wp_cache_flush();
    }
}