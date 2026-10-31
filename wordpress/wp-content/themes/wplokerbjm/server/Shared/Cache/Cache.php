<?php
namespace WPLokerBJM\Shared\Cache;

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * Object Cache management
 *
 * This class provides access to WordPress object cache for high-performance operations.
 * Supports both individual and bulk operations, and conditional operations.
 *
 * Redis-specific advanced features (pattern deletion, direct connection) are
 * handled by WPLokerBJM\Adapter\Redis.
 */
class Cache
{

    /**
     * Set a value in object cache.
     *
     * @param key-of<\WPLokerBJM\Shared\Cache\CacheKey::*> $key The cache key.
     * @param mixed $value The value to store.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return bool True if the value was set, false otherwise.
     */
    public static function set(string $key, $value, $expiration = 0): bool
    {
        try {
            if (!function_exists('wp_cache_set')) {
                return false;
            }
            $result = wp_cache_set($key, $value, CacheKey::OBJECT_CACHE_PREFIX, $expiration);
            if ($result) {
                CacheRegistry::record($key);
            }
            Logger::info('Cache', "Cache set result for key '{$key}': " . ($result ? 'success' : 'failure'));
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::set error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a value from object cache.
     *
     * @template T
     * @param key-of<\WPLokerBJM\Shared\Cache\CacheKey::*> $key The cache key.
     * @return T|false The cached value or false if not found.
     */
    public static function get(string $key): mixed
    {
        try {
            if (!function_exists('wp_cache_get')) {
                return false;
            }
            $result = wp_cache_get($key, CacheKey::OBJECT_CACHE_PREFIX);
            SharedUtils::isDevelopment() && Logger::info('Cache', "Cache get result for key '{$key}': " . ($result ? 'success' : 'failure'));
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::get error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a value from object cache.
     *
     * @param key-of<\WPLokerBJM\Shared\Cache\CacheKey::*> $key The cache key.
     * @return bool True if the value was deleted, false otherwise.
     */
    public static function delete(string $key): bool
    {
        try {
            if (!function_exists('wp_cache_delete')) {
                return false;
            }
            $result = wp_cache_delete($key, CacheKey::OBJECT_CACHE_PREFIX);
            Logger::info('Cache', "Cache delete result for key '{$key}': " . ($result ? 'success' : 'failure'));
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::delete error: ' . $e->getMessage());
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
            $results = wp_cache_set_multiple($data, CacheKey::OBJECT_CACHE_PREFIX, $expiration);
            foreach ($results as $key => $result) {
                if ($result) {
                    CacheRegistry::record((string) $key);
                }
            }
            return $results;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::setMultiple error: ' . $e->getMessage());
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
            Logger::info('Cache', "Deleting multiple cache keys: " . implode(', ', $keys) . " with group: '" . CacheKey::OBJECT_CACHE_PREFIX . "'");
            $results = wp_cache_delete_multiple($keys, CacheKey::OBJECT_CACHE_PREFIX);
            Logger::info('Cache', "Cache deleteMultiple results: " . json_encode($results));
            return $results;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::deleteMultiple error: ' . $e->getMessage());
            return array_fill_keys($keys, false);
        }
    }

    /**
     * Add a value to object cache only if the key doesn't already exist.
     *
     * @param key-of<\WPLokerBJM\Shared\Cache\CacheKey::*> $key The cache key.
     * @param mixed $value The value to store.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return bool True if the value was added, false if the key already exists or on failure.
     */
    public static function add(string $key, $value, int $expiration = 0): bool
    {
        try {
            if (!function_exists('wp_cache_add')) {
                return false;
            }
            $result = wp_cache_add($key, $value, CacheKey::OBJECT_CACHE_PREFIX, $expiration);
            if ($result) {
                CacheRegistry::record($key);
            }
            Logger::info('Cache', "Cache add result for key '{$key}': " . ($result ? 'success' : 'failure'));
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::add error: ' . $e->getMessage());
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
            $results = wp_cache_add_multiple($data, CacheKey::OBJECT_CACHE_PREFIX, $expiration);
            return $results;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::addMultiple error: ' . $e->getMessage());
            return array_fill_keys(array_keys($data), false);
        }
    }

    /**
     * Increment a cache value atomically.
     *
     * @param key-of<\WPLokerBJM\Shared\Cache\CacheKey::*> $key The cache key.
     * @param int $value The value to increment by. Default 1.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return int|false The new incremented value, or false if increment failed.
     */
    public static function increment(string $key, int $value = 1, int $expiration = 0): int|false
    {
        try {
            if (!function_exists('wp_cache_incr')) {
                $current = (int) self::get($key);
                $new_value = $current + $value;
                self::set($key, $new_value, $expiration);
                return $new_value;
            }
            Logger::info('Cache', "Incrementing cache key: '{$key}' by {$value} with group: '" . CacheKey::OBJECT_CACHE_PREFIX . "' and expiration: {$expiration}");
            $result = wp_cache_incr($key, $value, CacheKey::OBJECT_CACHE_PREFIX);
            if ($result !== false) {
                if ($expiration > 0) {
                    self::set($key . '_expires', time() + $expiration, 0);
                }
                Logger::info('Cache', "Cache increment result for key '{$key}': {$result}");
                return $result;
            } else {
                Logger::error('Cache', 'Object cache increment failed (returned false)');
                return false;
            }
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::increment error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrement a cache value atomically.
     *
     * @param key-of<\WPLokerBJM\Shared\Cache\CacheKey::*> $key The cache key.
     * @param int $value The value to decrement by. Default 1.
     * @return int|false The new decremented value, or false if decrement failed.
     */
    public static function decrement(string $key, int $value = 1): int|false
    {
        try {
            if (!function_exists('wp_cache_decr')) {
                $current = (int) self::get($key);
                $new_value = $current - $value;
                self::set($key, $new_value, 0);
                return $new_value;
            }
            Logger::info('Cache', "Decrementing cache key: '{$key}' by {$value} with group: '" . CacheKey::OBJECT_CACHE_PREFIX . "'");
            $current = self::get($key);
            if ($current === false) {
                self::set($key, 0, 0);
            }
            $result = wp_cache_decr($key, $value, CacheKey::OBJECT_CACHE_PREFIX);
            if ($result !== false) {
                Logger::info('Cache', "Cache decrement result for key '{$key}': {$result}");
                return $result;
            } else {
                Logger::error('Cache', 'Object cache decrement failed (returned false)');
                return false;
            }
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::decrement error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Replace the contents of the cache with new data.
     *
     * @param key-of<\WPLokerBJM\Shared\Cache\CacheKey::*> $key The cache key.
     * @param mixed $value The value to store.
     * @param int $expiration Time until expiration in seconds. Default 0 (no expiration).
     * @return bool True if the value was replaced, false if the key doesn't exist or on failure.
     */
    public static function replace(string $key, $value, int $expiration = 0): bool
    {
        try {
            if (!function_exists('wp_cache_replace')) {
                return false;
            }
            Logger::info('Cache', "Replacing cache key: '{$key}' with group: '" . CacheKey::OBJECT_CACHE_PREFIX . "' and expiration: {$expiration}");
            $result = wp_cache_replace($key, $value, CacheKey::OBJECT_CACHE_PREFIX, $expiration);
            if ($result) {
                CacheRegistry::record($key);
            }
            Logger::info('Cache', "Cache replace result for key '{$key}': " . ($result ? 'success' : 'failure'));
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::replace error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get multiple values from object cache.
     *
     * @template T
     * @param key-of<\WPLokerBJM\Shared\Cache\CacheKey::*>[] $keys Array of cache keys to retrieve.
     * @return array<string, T|false> Array of cache values, keyed by cache key.
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
            $results = wp_cache_get_multiple($keys, CacheKey::OBJECT_CACHE_PREFIX);
            return $results;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::getMultiple error: ' . $e->getMessage());
            return array_fill_keys($keys, false);
        }
    }

    /**
     * Flush all cache entries for a specific group.
     *
     * @param \WPLokerBJM\Shared\Cache\CacheKey::OBJECT_CACHE_PREFIX $group The cache group to flush.
     * @return bool True if the group was flushed, false otherwise.
     */
    public static function flushGroup(string $group): bool
    {
        try {
            if (!function_exists('wp_cache_flush_group')) {
                return false;
            }
            $result = wp_cache_flush_group($group);
            Logger::info('Cache', "Cache flushGroup result for group '{$group}': " . ($result ? 'success' : 'failure'));
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::flushGroup error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Flush all cache entries.
     *
     * @return bool True if the cache was flushed, false otherwise.
     */
    public static function flushAll(): bool
    {
        try {
            if (!function_exists('wp_cache_flush')) {
                return false;
            }
            $result = wp_cache_flush();
            Logger::info('Cache', "Cache flushAll result: " . ($result ? 'success' : 'failure'));
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::flushAll error: ' . $e->getMessage());
            return false;
        }
    }


}