<?php

declare(strict_types=1);

namespace WPLokerBJM\Shared\Cache;

use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Configs\Credential\CredentialConfig;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Records object-cache keys so they can be purged by prefix.
 *
 * LiteSpeed Cache v7.x hashes object-cache keys ({LSOC_PREFIX}:{sha256}),
 * making Redis glob patterns useless for purge. Every key written through
 * Cache::set()/add()/replace() for a tracked prefix (see
 * {@see CacheKey::PATTERN_PURGE_PREFIXES}) is therefore recorded in a
 * per-prefix registry SET, later consumed by
 * {@see RedisAdapter::deletePattern()}.
 *
 * Static facade: the RedisAdapter is lazily created from CredentialConfig
 * (same arguments the DI factory uses) so hot-path cache writes never
 * touch the container.
 */
final class CacheRegistry
{
    private static ?RedisAdapter $adapter = null;

    /**
     * Record a logical cache key in its prefix's purge registry.
     *
     * No-op for prefixes that are not pattern-purged, under PHPUnit
     * (WPLOKERBJM_TEST_ENV — wp_cache_* is mocked in-memory there, so real
     * Redis must not receive registry writes), and on any Redis failure —
     * registry bookkeeping must never block cache writes.
     *
     * Note: covers the set/add/replace write paths. Counters written via
     * wp_cache_incr/decr (e.g. job_last_modified) are exact-deleted instead
     * and intentionally not tracked.
     */
    public static function record(string $key): void
    {
        if (defined('WPLOKERBJM_TEST_ENV') && WPLOKERBJM_TEST_ENV) {
            return;
        }

        $prefix = self::matchingPrefix($key);
        if ($prefix === null) {
            return;
        }

        $adapter = self::adapter();
        if ($adapter === null) {
            return;
        }

        try {
            $adapter->registerKey($prefix, $adapter->buildObjectCacheKey($key));
        } catch (\Throwable $e) {
            Logger::warning('CacheRegistry', 'Failed to record key "' . $key . '": ' . $e->getMessage());
        }
    }

    /**
     * Resolve the tracked prefix a key belongs to, if any.
     */
    private static function matchingPrefix(string $key): ?string
    {
        foreach (CacheKey::PATTERN_PURGE_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    private static function adapter(): ?RedisAdapter
    {
        try {
            return self::$adapter ??= new RedisAdapter(CredentialConfig::RedisCredential());
        } catch (\Throwable $e) {
            Logger::warning('CacheRegistry', 'Unable to create RedisAdapter: ' . $e->getMessage());
            return null;
        }
    }
}
