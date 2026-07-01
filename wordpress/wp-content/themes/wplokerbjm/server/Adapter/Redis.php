<?php
declare(strict_types=1);
namespace WPLokerBJM\Adapter;

use WPLokerBJM\Shared\Cache\CacheKey;
/**
 * Redis connection adapter for advanced operations.
 *
 * Provides direct Redis access for pattern-based key deletion and other
 * advanced operations not supported by the WordPress object cache API.
 *
 * Credentials are injected via the constructor using PHP-DI (see Factory.php).
 * A static singleton is registered so WordPress hook subscribers that are
 * not resolved through the container can still call Redis::deletePattern()
 * and Redis::getConnection() as static methods.
 */
class RedisAdapter
{
    private static ?self $instance = null;
    private ?\Redis $connection = null;

    public function __construct(private array $credentials)
    {
        self::setInstance($this);
    }

    /**
     * Override the singleton instance.
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get a connected Redis instance for direct Redis operations.
     *
     * @return \Redis|false Connected Redis instance or false on failure.
     */
    public static function getConnection(): \Redis|false
    {
        return self::$instance?->resolveConnection() ?? false;
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
    public static function deletePattern(array $patterns): int|false
    {
        return self::$instance?->executeDeletePattern($patterns) ?? false;
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
                return false;
            }

            $host     = $this->credentials['host'];
            $port     = $this->credentials['port'];
            $password = $this->credentials['password'];
            $database = $this->credentials['database'];
            $sock     = $this->credentials['sock'];

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
                return false;
            }

            // Derive the salt used by LiteSpeed Cache's object cache drop-in
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

            // Collect all matching keys across patterns
            $allKeys = [];

            foreach ($patterns as $pattern) {
                $fullPattern = $salt . CacheKey::OBJECT_CACHE_PREFIX . '.' . $pattern;
                $keys = $redis->keys($fullPattern);
                $allKeys = array_merge($allKeys, $keys);
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
}
