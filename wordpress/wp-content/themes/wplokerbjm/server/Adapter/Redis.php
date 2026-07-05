<?php
declare(strict_types=1);
namespace WPLokerBJM\Adapter;

use WPLokerBJM\Shared\Cache\CacheKey;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Redis connection adapter for advanced operations.
 *
 * Provides direct Redis access for pattern-based key deletion and other
 * advanced operations not supported by the WordPress object cache API.
 *
 * Credentials are injected via the constructor using PHP-DI.
 * @see \WPLokerBJM\Core\Container\Definitions\Factory
 */
class RedisAdapter
{
    private ?\Redis $connection = null;

    public function __construct(private array $credentials)
    {
    }

    /**
     * Get a connected Redis instance for direct Redis operations.
     *
     * @return \Redis|false Connected Redis instance or false on failure.
     */
    public function getConnection(): \Redis|false
    {
        return $this->resolveConnection();
    }

    /**
     * Delete cache keys matching multiple patterns.
     *
     * Builds the full key pattern using the LiteSpeed salt and the
     * application's object-cache prefix, then performs an asynchronous
     * unlink on all matched keys.
     *
     * @param string[] $patterns Array of glob patterns (e.g. ['job_grid_*', 'search_sql_*']).
     * @return int|false Number of keys deleted, or false on error.
     */
    public function deletePattern(array $patterns): int|false
    {
        return $this->executeDeletePattern($patterns);
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

            $host = $this->credentials['host'];
            $port = $this->credentials['port'];
            $password = $this->credentials['password'];
            $database = $this->credentials['database'];
            $sock = $this->credentials['sock'];

            $redis = new \Redis();

            if ($sock && file_exists($sock)) {
                $connected = $redis->connect($sock);
            } else {
                $connected = $redis->connect($host, $port);
            }

            if (!$connected) {
                return false;
            }

            if ($password && !$redis->auth($password)) {
                return false;
            }

            if ($database !== null && !$redis->select($database)) {
                return false;
            }

            $this->connection = $redis;
            return $redis;
        } catch (\Exception $e) {
            Logger::error('Redis', 'Failed to connect to Redis: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute pattern-based deletion against Redis.
     */
    private function executeDeletePattern(array $patterns): int|false
    {
        try {
            $redis = $this->resolveConnection();
            if (!$redis) {
                Logger::warning('Redis', 'Unable to resolve Redis connection.');
                return false;
            }

            // Collect all matching keys across patterns
            $allKeys = [];

            foreach ($patterns as $pattern) {
                $fullPattern = $this->getSalt() . CacheKey::OBJECT_CACHE_PREFIX . '.' . $pattern;
                $iterator = null;

                while (($keys = $redis->scan($iterator, $fullPattern, 100)) !== false) {
                    if (!empty($keys)) {
                        $allKeys = array_merge($allKeys, $keys);
                    }
                    if ($iterator === 0 || $iterator === '0') {
                        break;
                    }
                }
            }
            if (empty($allKeys)) {
                return 0;
            }

            // Asynchronous unlink is faster than del for bulk operations
            return $redis->unlink($allKeys);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Redis cache salt used by LiteSpeed Cache's object cache drop-in.
     * @return string
     */
    private function getSalt(): string
    {
        $wp_content_dir = defined('WP_CONTENT_DIR')
            ? WP_CONTENT_DIR
            : dirname(get_stylesheet_directory()) . '/..';

        $lscwp_dir = (defined('WP_PLUGIN_DIR')
            ? WP_PLUGIN_DIR
            : $wp_content_dir . '/plugins') . '/litespeed-cache/';

        $cls_file = $lscwp_dir . 'src/object-cache-wp.cls.php';

        if (defined('LSOC_PREFIX') && is_string(LSOC_PREFIX) && !empty(LSOC_PREFIX)) {
            $salt = LSOC_PREFIX;
        } else {
            $salt = substr(md5($cls_file), -5);
        }
        return $salt;
    }
}
