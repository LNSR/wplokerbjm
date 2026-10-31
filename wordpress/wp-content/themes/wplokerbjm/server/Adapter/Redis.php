<?php

declare(strict_types=1);

namespace WPLokerBJM\Adapter;

use WPLokerBJM\Configs\Credential\RedisCred;
use WPLokerBJM\Shared\Cache\CacheKey;
use WPLokerBJM\Shared\Log\Logger;
use WP_Object_Cache;

/**
 * Redis connection adapter for advanced operations.
 *
 * Provides direct Redis access for pattern-based key deletion and other
 * advanced operations not supported by the WordPress object cache API.
 *
 * Since LiteSpeed Cache v7.x stores object-cache keys as opaque hashes
 * ({LSOC_PREFIX}:{sha256(...)}), glob patterns can no longer address keys
 * directly. Key addresses are instead recorded at write time in per-prefix
 * registry SETs (see registerKey() / CacheRegistry) and deletePattern()
 * purges from those registries.
 *
 * Credentials are injected via the constructor using PHP-DI.
 * @see \WPLokerBJM\Core\Container\Definitions\Factory
 */
class RedisAdapter
{
    /** Prefix for registry SET keys tracking exact Redis keys per logical prefix. */
    private const REGISTRY_KEY_PREFIX = 'wplokerbjm:registry:';

    private ?\Redis $connection = null;
    public function __construct(private RedisCred $credentials) {}

    /**
     * Delete cache keys matching multiple patterns.
     *
     * For each pattern the matching prefix registry SET is read (SMEMBERS),
     * every recorded key is asynchronously unlinked, and the registry SET
     * itself is dropped. Patterns are accepted in glob form (e.g.
     * ['job_grid_*', 'search_sql_*']); the trailing glob is stripped to
     * resolve the registry key.
     *
     * @param string[] $patterns Array of glob patterns (e.g. ['job_grid_*', 'search_sql_*']).
     * @return int|false Number of keys deleted, or false on error.
     */
    public function deletePattern(array $patterns): int|false
    {
        return $this->executeDeletePattern($patterns);
    }

    /**
     * Compute the exact Redis key LSCWP v7.x stores for a logical cache key.
     *
     * Mirrors @see WP_Object_Cache::_key() in
     * litespeed-cache/src/object-cache-wp.cls.php:
     * LSOC_PREFIX . ':' . hash('sha256',
     *     strlen($prefix).':'.$prefix . strlen($group).':'.$group . ':'.$key)
     *
     * @param string $key Logical cache key written via Cache::set().
     */
    public function buildObjectCacheKey(string $key): string
    {
        $group = CacheKey::OBJECT_CACHE_PREFIX;
        $blogPrefix = '';

        // Multisite: LSCWP prefixes with 'blogId:' for non-global groups.
        if (function_exists('is_multisite') && is_multisite()) {
            $blogPrefix = (string) get_current_blog_id() . ':';
        }

        return $this->getSalt()
            . ':'
            . hash('sha256', strlen($blogPrefix) . ':' . $blogPrefix . strlen($group) . ':' . $group . ':' . $key);
    }

    /**
     * Registry SET key holding the exact Redis keys for a logical prefix.
     */
    public static function registryKeyFor(string $prefix): string
    {
        return self::REGISTRY_KEY_PREFIX . rtrim($prefix, '*');
    }

    /**
     * Record an exact Redis key under a prefix's purge registry.
     *
     * Idempotent (Redis SET semantics). Never throws — registry bookkeeping
     * must not break cache writes.
     *
     * @return bool True when recorded (or Redis unreachable → false).
     */
    public function registerKey(string $prefix, string $redisKey): bool
    {
        try {
            $redis = $this->resolveConnection();
            if (!$redis) {
                return false;
            }

            return $redis->sAdd(self::registryKeyFor($prefix), $redisKey) !== false;
        } catch (\Exception $e) {
            Logger::warning('Redis', 'Failed to register cache key: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve (or reuse) a Redis connection using stored credentials.
     */
    private function resolveConnection(): \Redis|false
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        try {
            if (!extension_loaded('redis')) {
                Logger::warning('Redis', 'Extension Redis is not loaded.');
                return false;
            }

            $credential = $this->credentials;

            $redis = new \Redis();
            $connected = $credential->sock && file_exists($credential->sock) ? $redis->connect($credential->sock) : $redis->connect($credential->host, (int) $credential->port);
            if (!$connected) {
                return false;
            }

            if ($credential->password && !$redis->auth($credential->password)) {
                return false;
            }

            if ($credential->database !== null && !$redis->select((int) $credential->database)) {
                return false;
            }

            return $this->connection = $redis;
        } catch (\Exception $e) {
            Logger::error('Redis', 'Failed to connect to Redis: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute registry-based deletion against Redis.
     */
    private function executeDeletePattern(array $patterns): int|false
    {
        try {
            $redis = $this->resolveConnection();
            if (!$redis) {
                Logger::warning('Redis', 'Unable to resolve Redis connection.');
                return false;
            }

            $deleted = 0;

            foreach ($patterns as $pattern) {
                $registryKey = self::registryKeyFor($pattern);
                $members = $redis->sMembers($registryKey);

                if (!empty($members)) {
                    // Asynchronous unlink is faster than del for bulk operations
                    $deleted += (int) $redis->unlink($members);
                }

                // Drop the registry regardless: members are only valid for this purge.
                $redis->del($registryKey);
            }

            return $deleted;
        } catch (\Exception $e) {
            Logger::error('Redis', 'Failed to delete cache patterns: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Redis cache salt used by LiteSpeed Cache's object cache drop-in.
     *
     * LSCWP v7.x defines LSOC_PREFIX at runtime as
     * hash('sha256', WP_CACHE_KEY_SALT . "\0" . DB_NAME . "\0" . $table_prefix),
     * which is honored when available. The fallback mirrors that algorithm
     * for non-WP contexts.
     */
    private function getSalt(): string
    {
        if (defined('LSOC_PREFIX') && is_string(LSOC_PREFIX) && !empty(LSOC_PREFIX)) {
            return LSOC_PREFIX;
        }

        global $table_prefix;

        $identity = (defined('WP_CACHE_KEY_SALT') && is_string(WP_CACHE_KEY_SALT) ? WP_CACHE_KEY_SALT : '')
            . "\0"
            . (defined('DB_NAME') ? DB_NAME : (defined('ABSPATH') ? ABSPATH : ''))
            . "\0"
            . (string) ($table_prefix ?? '');

        return hash('sha256', $identity);
    }
}
