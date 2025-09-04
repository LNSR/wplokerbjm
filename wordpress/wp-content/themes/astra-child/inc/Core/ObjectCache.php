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
        try {
            if (!function_exists('wp_cache_set')) {
                return false;
            }
            return wp_cache_set($key, $value, self::OBJECT_CACHE_PREFIX, $expiration);
        } catch (\Exception $e) {
            error_log('ObjectCache::set error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a value from object cache.
     *
     * @param string $key The cache key.
     * @return mixed The cached value or false if not found.
     */
    public static function get($key): mixed
    {
        try {
            if (!function_exists('wp_cache_get')) {
                return false;
            }
            return wp_cache_get($key, self::OBJECT_CACHE_PREFIX);
        } catch (\Exception $e) {
            error_log('ObjectCache::get error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a value from object cache.
     *
     * @param string $key The cache key.
     * @return bool True if the value was deleted, false otherwise.
     */
    public static function delete($key): bool
    {
        try {
            if (!function_exists('wp_cache_delete')) {
                return false;
            }
            return wp_cache_delete($key, self::OBJECT_CACHE_PREFIX);
        } catch (\Exception $e) {
            error_log('ObjectCache::delete error: ' . $e->getMessage());
            return false;
        }
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
        try {
            if (!function_exists('wp_cache_incr')) {
                return Cache::incrementTransient($key, $value, $expiration);
            }
            $current = self::get($key);
            if ($current === false) {
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
        } catch (\Exception $e) {
            error_log('ObjectCache::increment error: ' . $e->getMessage());
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
        try {
            if (!function_exists('wp_cache_decr')) {
                return Cache::decrementTransient($key, $value);
            }
            $current = self::get($key);
            if ($current === false) {
                self::set($key, 0, 0);
            }
            $result = wp_cache_decr($key, $value, self::OBJECT_CACHE_PREFIX);
            if ($result !== false) {
                return $result;
            } else {
                error_log('Object cache decrement failed (returned false)');
                return Cache::decrementTransient($key, $value);
            }
        } catch (\Exception $e) {
            error_log('ObjectCache::decrement error: ' . $e->getMessage());
            return Cache::decrementTransient($key, $value);
        }
    }

    public static function flush(): bool
    {
        try {
            if (!function_exists('wp_cache_flush')) {
                return false;
            }
            return wp_cache_flush();
        } catch (\Exception $e) {
            error_log('ObjectCache::flush error: ' . $e->getMessage());
            return false;
        }
    }
}