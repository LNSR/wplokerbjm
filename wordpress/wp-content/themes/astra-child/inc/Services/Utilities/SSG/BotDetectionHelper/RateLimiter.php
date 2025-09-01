<?php

namespace AstraChild\Services\Utilities\SSG\BotDetectionHelper;

use AstraChild\Core\ObjectCache;

/**
 * RateLimiter
 *
 * Handles rate limiting logic for bot detection
 */
class RateLimiter
{
    /**
     * Lightweight recent-request rate check using transients (automatically uses Redis when available).
     * Returns a small score based on the number of requests from the IP in the last minute.
     */
    public function getRateScore(string $ip): int
    {
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return 0;
        }

        $cacheKey = 'ssg_hits_' . $ip;

        $count = ObjectCache::increment($cacheKey, 1, 120);

        if ($count === false) {
            return 0;
        }

        // Conservative scoring: only add a little weight unless rate is very high
        if ($count > 100) {
            return 3;
        }
        if ($count > 50) {
            return 2;
        }
        if ($count > 20) {
            return 1;
        }

        return 0;
    }
}