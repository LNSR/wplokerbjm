<?php
namespace WPLokerBJM\Shared\Cache;

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Configs\CredentialConfig;

/**
 * Object Cache management
 *
 * This class provides direct access to WordPress object cache (e.g., Redis) for high-performance operations.
 * Supports both individual and bulk operations, conditional operations, and direct Redis access for advanced features.
 */
class Cache
{
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
            $result = wp_cache_set($key, $value, CacheKey::OBJECT_CACHE_PREFIX, $expiration);
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::set error: ' . $e->getMessage());
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
            $result = wp_cache_get($key, CacheKey::OBJECT_CACHE_PREFIX);
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::get error: ' . $e->getMessage());
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
            Logger::info('Cache', "Deleting cache key: '{$key}' with group: '" . CacheKey::OBJECT_CACHE_PREFIX . "'");
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
            $result = wp_cache_add($key, $value, CacheKey::OBJECT_CACHE_PREFIX, $expiration);
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
            Logger::info('Cache', "Replacing cache key: '{$key}' with group: '" . CacheKey::OBJECT_CACHE_PREFIX . "' and expiration: {$expiration}");
            $result = wp_cache_replace($key, $value, CacheKey::OBJECT_CACHE_PREFIX, $expiration);
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
     * @param string $group The cache group to flush.
     * @return bool True if the group was flushed, false otherwise.
     */
    public static function flushGroup(string $group): bool
    {
        try {
            if (!function_exists('wp_cache_flush_group')) {
                return false;
            }
            $result = wp_cache_flush_group($group);
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
            Logger::info('Cache', "Flushing all cache");
            $result = wp_cache_flush();
            Logger::info('Cache', "Cache flushAll result: " . ($result ? 'success' : 'failure'));
            return $result;
        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::flushAll error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete cache keys matching multiple patterns (Redis-specific).
     * This method provides direct Redis access for pattern-based deletion.
     * Only works when Redis is the cache backend.
     *
     * @param string[] $patterns Array of patterns to match (e.g., ['prefix1_*', 'prefix2_*']).
     * @return int Number of keys deleted, or false on error.
     */
    public static function deletePattern(array $patterns): int|false
    {
        Logger::info('Cache', "Cache::deletePattern called with patterns: " . implode(', ', $patterns));

        try {
            // Get Redis connection
            $redis = self::getRedisConnection();
            if (!$redis) {
                Logger::error('Cache', 'Cache::deletePattern: Redis connection failed');
                return false;
            }

            // Build full pattern with LSC's prefix (use constant if available, else replicate)
            $wp_content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(get_stylesheet_directory()) . '/..';
            $lscwp_dir = (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : $wp_content_dir . '/plugins') . '/litespeed-cache/';
            $cls_file = $lscwp_dir . 'src/object-cache-wp.cls.php';
            if (defined('LSOC_PREFIX') && is_string(LSOC_PREFIX) && !empty(LSOC_PREFIX)) {
                $salt = LSOC_PREFIX;
                Logger::info('Cache', "Using LSOC_PREFIX constant: '{$salt}'");
            } else {
                $salt = substr(md5($cls_file), -5);
                Logger::warning('Cache', "LSOC_PREFIX not defined or invalid, falling back to computed salt: '{$salt}'");
            }

            $allKeys = [];
            foreach ($patterns as $pattern) {
                $fullPattern = $salt . CacheKey::OBJECT_CACHE_PREFIX . '.' . $pattern;
                $keys = $redis->keys($fullPattern);
                $allKeys = array_merge($allKeys, $keys);
            }

            Logger::info('Cache', "Cache::deletePattern: Found " . count($allKeys) . " keys matching patterns: " . implode(', ', $patterns));

            if (empty($allKeys)) {
                return 0;
            }

            // Use unlink for asynchronous deletion (faster)
            $deletedCount = $redis->unlink($allKeys);

            Logger::info('Cache', "Cache::deletePattern: Unlinked {$deletedCount} keys matching patterns: " . implode(', ', $patterns));

            return $deletedCount;

        } catch (\Exception $e) {
            Logger::error('Cache', 'Cache::deletePattern error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a connected Redis instance for direct Redis operations.
     * ! Only use this for advanced operations not supported by WP object cache.
     *
     * @return \Redis|false Connected Redis instance or false on failure.
     */
    public static function getRedisConnection(): \Redis|false
    {
        try {
            // Check if Redis extension is available
            if (!extension_loaded('redis')) {
                return false;
            }

            $credentials = CredentialConfig::RedisCredential();
            $host = $credentials['host'];
            $port = $credentials['port'];
            $password = $credentials['password'];
            $database = $credentials['database'];
            $sock = $credentials['sock'];

            $redis = new \Redis();

            if ($sock && file_exists($sock)) {
                $connected = $redis->connect($sock);
            } else {
                $connected = $redis->connect($host, $port);
            }

            if (!$connected) {
                return false;
            }

            // Authenticate if password is set
            if ($password) {
                if (!$redis->auth($password)) {
                    return false;
                }
            }

            // Select database
            if ($database !== null && !$redis->select($database)) {
                return false;
            }

            return $redis;

        } catch (\Exception $e) {
            return false;
        }
    }
}

/**
 * Cache key definitions
 *
 * Centralized definition of all cache keys and prefixes used in the application.
 * Helps avoid typos and ensures consistency across the codebase.
 */
class CacheKey
{
    // General cache prefixes and keys
    const OBJECT_CACHE_PREFIX = 'wplokerbjm_obj_';
    const COMPILED_CONTAINER_HASH = 'compiled_container_hash';
    const THEME_DATA = 'theme_data';

    // Job-related
    const JOB_DATA_PREFIX = 'job_data_';
    const JOB_SCHEMA_PREFIX = 'job_schema_';
    const JOB_LAST_MODIFIED = 'job_last_modified';

    // GraphQL API
    const GRAPHQL_JOB_CARD_PREFIX = 'graphql_job_card_';
    const GRAPHQL_JOB_DETAIL_PREFIX = 'graphql_job_detail_';
    const GRAPHQL_JOB_SCHEMA_BATCH_PREFIX = 'graphql_job_schema_batch_';
    const AUTO_SUGGESTION_PREFIX = 'auto_suggestion_';
    const LOAD_MORE_PREFIX = 'load_more_';
    const DYNAMIC_SEARCH_PREFIX = 'dynamic_search_';
    const SYNC_BOOKMARK_PREFIX = 'sync_bookmark_';
    const RANKMATH_HEAD_PREFIX = 'rankmath_head_';

    // Presenters
    const CAROUSEL_JOBS = 'carousel_jobs';
    const JOB_GRID_PREFIX = 'job_grid_';

    // Taxonomy
    const TAXONOMY_LAST_MODIFIED = 'taxonomy_last_modified';
    const COMPANY_SEARCH_PREFIX = 'company_search_';
    const ALL_TAXONOMY_TERMS = 'all_taxonomy_terms';
    const POST_TAXONOMIES_PREFIX = 'post_taxonomies_';
    const TAXONOMY_DEPTH_HANDLE = 'taxonomy_depth_handle';
    const TAXONOMY_DEPTH_LOKASI = 'taxonomy_depth_lokasi';
    const TAXONOMY_DEPTH_GENDER = 'taxonomy_depth_gender';
    const TAXONOMY_DEPTH_PENDIDIKAN = 'taxonomy_depth_pendidikan';

    // Query Builders
    const SEARCH_SQL_PREFIX = 'search_sql_';

    // Enqueue/Assets
    const VITE_MANIFEST = 'vite_manifest';
    const PRELOAD_URLS_PREFIX = 'preload_urls_';
    const PRELOAD_LINK_HEADER_PREFIX = 'preload_link_header_';
    const TRANSITIVE_ASSETS_PREFIX = 'transitive_assets_';

    // Autowire Scanner
    const AUTOWIRE_SCANNER_PREFIX = 'autowire_scanner_';

    // RankMath
    const RANKMATH_SITEMAP_DEBOUNCE_PREFIX = 'rankmath_sitemap_debounce_';
    const RANKMATH_SITEMAP_DELETE_DEBOUNCE_PREFIX = 'rankmath_sitemap_delete_debounce_';
    const RANKMATH_FULL_SITEMAP_DEBOUNCE = 'rankmath_full_sitemap_debounce';
}