<?php
namespace WPLokerBJM\Core;

/**
 * Object Cache management
 *
 * This class provides direct access to WordPress object cache (e.g., Redis) for high-performance operations.
 * Supports both individual and bulk operations, conditional operations, and direct Redis access for advanced features.
 */
class Cache
{
    const OBJECT_CACHE_PREFIX = 'wplokerbjm_obj_';

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
            error_log('Cache::set error: ' . $e->getMessage());
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
            error_log('Cache::get error: ' . $e->getMessage());
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
            error_log('Cache::delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set multiple values in object cache.
     *
     * @param array $data Array of key => value pairs to cache.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return array Array of results, keyed by cache key.
     */
    public static function setMultiple(array $data, int $expiration = 0): array
    {
        try {
            if (!function_exists('wp_cache_set_multiple')) {
                // Fallback to individual sets
                $results = [];
                foreach ($data as $key => $value) {
                    $results[$key] = self::set($key, $value, $expiration);
                }
                return $results;
            }
            return wp_cache_set_multiple($data, self::OBJECT_CACHE_PREFIX, $expiration);
        } catch (\Exception $e) {
            error_log('Cache::setMultiple error: ' . $e->getMessage());
            return array_fill_keys(array_keys($data), false);
        }
    }

    /**
     * Delete multiple values from object cache.
     *
     * @param string[] $keys Array of cache keys to delete.
     * @return array Array of deletion results, keyed by cache key.
     */
    public static function deleteMultiple(array $keys): array
    {
        try {
            if (!function_exists('wp_cache_delete_multiple')) {
                // Fallback to individual deletions
                $results = [];
                foreach ($keys as $key) {
                    $results[$key] = self::delete($key);
                }
                return $results;
            }
            return wp_cache_delete_multiple($keys, self::OBJECT_CACHE_PREFIX);
        } catch (\Exception $e) {
            error_log('Cache::deleteMultiple error: ' . $e->getMessage());
            return array_fill_keys($keys, false);
        }
    }

    /**
     * Add a value to object cache only if the key doesn't already exist.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to store.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return bool True if the value was added, false if the key already exists or on failure.
     */
    public static function add($key, $value, $expiration = 0): bool
    {
        try {
            if (!function_exists('wp_cache_add')) {
                return false;
            }
            return wp_cache_add($key, $value, self::OBJECT_CACHE_PREFIX, $expiration);
        } catch (\Exception $e) {
            error_log('Cache::add error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add multiple values to object cache only if the keys don't already exist.
     *
     * @param array $data Array of key => value pairs to cache.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return array Array of results, keyed by cache key.
     */
    public static function addMultiple(array $data, int $expiration = 0): array
    {
        try {
            if (!function_exists('wp_cache_add_multiple')) {
                // Fallback to individual adds
                $results = [];
                foreach ($data as $key => $value) {
                    $results[$key] = self::add($key, $value, $expiration);
                }
                return $results;
            }
            return wp_cache_add_multiple($data, self::OBJECT_CACHE_PREFIX, $expiration);
        } catch (\Exception $e) {
            error_log('Cache::addMultiple error: ' . $e->getMessage());
            return array_fill_keys(array_keys($data), false);
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
                $current = (int) self::get($key);
                $new_value = $current + $value;
                self::set($key, $new_value, $expiration);
                return $new_value;
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
                return false;
            }
        } catch (\Exception $e) {
            error_log('Cache::increment error: ' . $e->getMessage());
            return false;
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
                $current = (int) self::get($key);
                $new_value = $current - $value;
                self::set($key, $new_value, 0);
                return $new_value;
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
                return false;
            }
        } catch (\Exception $e) {
            error_log('Cache::decrement error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Replace the contents of the cache with new data.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to store.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return bool True if the value was replaced, false if the key doesn't exist or on failure.
     */
    public static function replace($key, $value, $expiration = 0): bool
    {
        try {
            if (!function_exists('wp_cache_replace')) {
                return false;
            }
            return wp_cache_replace($key, $value, self::OBJECT_CACHE_PREFIX, $expiration);
        } catch (\Exception $e) {
            error_log('Cache::replace error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get multiple values from object cache.
     *
     * @param string[] $keys Array of cache keys to retrieve.
     * @return array Array of cache values, keyed by cache key.
     */
    public static function getMultiple(array $keys): array
    {
        try {
            if (!function_exists('wp_cache_get_multiple')) {
                // Fallback to individual gets
                $results = [];
                foreach ($keys as $key) {
                    $results[$key] = self::get($key);
                }
                return $results;
            }
            return wp_cache_get_multiple($keys, self::OBJECT_CACHE_PREFIX);
        } catch (\Exception $e) {
            error_log('Cache::getMultiple error: ' . $e->getMessage());
            return array_fill_keys($keys, false);
        }
    }

    /**
     * Flush all cache entries for a specific group.
     *
     * @param string $group The cache group to flush.
     * @return bool True if the group was flushed, false otherwise.
     */
    public static function flushGroup(string $group): bool
    {
        try {
            if (!function_exists('wp_cache_flush_group')) {
                return false;
            }
            return wp_cache_flush_group($group);
        } catch (\Exception $e) {
            error_log('Cache::flushGroup error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if the cache backend supports a specific feature.
     *
     * @param string $feature The feature to check for (e.g., 'add_multiple', 'set_multiple').
     * @return bool True if the feature is supported, false otherwise.
     */
    public static function supports(string $feature): bool
    {
        try {
            if (!function_exists('wp_cache_supports')) {
                return false;
            }
            return wp_cache_supports($feature);
        } catch (\Exception $e) {
            error_log('Cache::supports error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete cache keys matching a pattern (Redis-specific).
     * This method provides direct Redis access for pattern-based deletion.
     * Only works when Redis is the cache backend.
     *
     * @param string $pattern Pattern to match (e.g., 'prefix_*').
     * @return int Number of keys deleted, or false on error.
     */
    public static function deletePattern(string $pattern): int|false
    {
        try {
            // Get Redis connection
            $redis = self::getRedisConnection();
            if (!$redis) {
                return false;
            }

            // Build full pattern with our prefix
            $fullPattern = self::OBJECT_CACHE_PREFIX . ':' . $pattern;

            $keys = $redis->keys($fullPattern);
            if (empty($keys)) {
                return 0;
            }

            // Use unlink for asynchronous deletion (faster)
            $deletedCount = $redis->unlink($keys);

            error_log("Cache::deletePattern: Unlinked {$deletedCount} keys matching pattern '{$pattern}'");

            return $deletedCount;

        } catch (\Exception $e) {
            error_log('Cache::deletePattern error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a connected Redis instance for direct Redis operations.
     * ! Only use this for advanced operations not supported by WP object cache.
     *
     * @return \Redis|false Connected Redis instance or false on failure.
     */
    private static function getRedisConnection(): \Redis|false
    {
        try {
            // Check if Redis extension is available
            if (!extension_loaded('redis')) {
                error_log('Cache::getRedisConnection: Redis extension not available');
                return false;
            }

            $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : null;
            $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : null;
            $password = defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : null;
            $database = defined('WP_REDIS_DATABASE') ? WP_REDIS_DATABASE : null;

            $redis = new \Redis();

            if (defined('WP_REDIS_SOCK') && file_exists(WP_REDIS_SOCK)) {
                $connected = $redis->connect(WP_REDIS_SOCK);
                error_log('Cache::getRedisConnection: Connecting to Redis via socket ' . WP_REDIS_SOCK);
            } else {
                $connected = $redis->connect($host, $port);
                error_log('Cache::getRedisConnection: Connecting to Redis at ' . $host . ':' . $port);
            }

            if (!$connected) {
                error_log('Cache::getRedisConnection: Failed to connect to Redis');
                return false;
            }

            // Authenticate if password is set
            if ($password) {
                if (!$redis->auth($password)) {
                    error_log('Cache::getRedisConnection: Redis authentication failed');
                    return false;
                }
            }

            // Select database
            if (!$redis->select($database)) {
                error_log('Cache::getRedisConnection: Failed to select Redis database');
                return false;
            }

            return $redis;

        } catch (\Exception $e) {
            error_log('Cache::getRedisConnection error: ' . $e->getMessage());
            return false;
        }
    }
}