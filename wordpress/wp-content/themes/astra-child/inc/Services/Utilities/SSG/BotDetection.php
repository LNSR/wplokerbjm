<?php

namespace AstraChild\Services\Utilities\SSG;

/**
 * BotDetection
 *
 * Advanced multi-factor bot detection system for WordPress.
 *
 * This class implements a comprehensive bot detection strategy using multiple
 * detection methods with different reliability levels. It employs a scoring
 * system for suspicious traffic while providing immediate flagging for known
 * legitimate bot IPs from official sources.
 *
 * Detection Methods (in order of execution):
 * 1. Official Bot IP Ranges - Immediate flagging (most reliable)
 * 2. Forward-confirmed Reverse DNS - Domain-based detection
 * 3. User Agent Keyword Analysis - Pattern matching
 * 4. HTTP Header Analysis - Suspicious header detection
 * 5. Rate Limiting - Request frequency analysis
 * 6. Custom Hooks - Extensibility for additional checks
 *
 * @package AstraChild\Services\Utilities\SSG
 */
class BotDetection
{
    /**
     * Constructor
     *
     * @param \AstraChild\Services\Utilities\SSG\BotDetectionHelper\BotRangeFetcher $botRangeFetcher Bot IP range checker and fetcher
     * @param \AstraChild\Services\Utilities\SSG\BotDetectionHelper\HeaderDetector $headerDetector HTTP header analyzer
     * @param \AstraChild\Services\Utilities\SSG\BotDetectionHelper\KeywordDetector $keywordDetector User agent keyword detector
     * @param \AstraChild\Services\Utilities\SSG\BotDetectionHelper\RateLimiter $rateLimiter Request rate limiter
     * @param \AstraChild\Services\Utilities\SSG\BotDetectionHelper\DnsResolver $dnsResolver DNS resolver and PTR analyzer
     */
    public function __construct(
        private \AstraChild\Services\Utilities\SSG\BotDetectionHelper\BotRangeFetcher $botRangeFetcher,
        private \AstraChild\Services\Utilities\SSG\BotDetectionHelper\HeaderDetector $headerDetector,
        private \AstraChild\Services\Utilities\SSG\BotDetectionHelper\KeywordDetector $keywordDetector,
        private \AstraChild\Services\Utilities\SSG\BotDetectionHelper\RateLimiter $rateLimiter,
        private \AstraChild\Services\Utilities\SSG\BotDetectionHelper\DnsResolver $dnsResolver
    ) {
    }

    /**
     * Check if the current visitor is a bot using multi-factor detection.
     *
     * This method implements a sophisticated bot detection algorithm that combines
     * multiple detection techniques with different reliability levels. The system
     * uses a scoring approach for most checks while providing immediate flagging
     * for known legitimate bot IPs.
     *
     * Detection Flow:
     * 1. Official bot IP ranges (immediate return true if matched)
     * 2. DNS-based detection (PTR record analysis)
     * 3. User agent pattern matching
     * 4. HTTP header analysis
     * 5. Request rate analysis
     * 6. Custom filter hooks
     *
     * Scoring Threshold:
     * - Score ≥ 3: Classified as bot
     * - Official bot IPs: Immediate classification (bypasses scoring)
     *
     * @return bool True if visitor is detected as a bot, false otherwise
     */
    public function isBot(): bool
    {
        // Extract request data from server variables
        /** @var string $userAgent Browser/client user agent string */
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        /** @var string $remoteAddr Client IP address (may be proxied) */
        $remoteAddr = $this->dnsResolver->getRealIp();

        /** @var string $referer HTTP referer header */
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        /** @var string $acceptLanguage Accept-Language header */
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        /** @var string $acceptHeader Accept header */
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

        /** @var string $acceptEncoding Accept-Encoding header */
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

        // Exclude our own SSG bot from being treated as a bot
        $ssgBotUserAgents = $this->keywordDetector::isSsgBotGeneration();
        foreach ($ssgBotUserAgents as $ssgBot) {
            if (stripos($userAgent, $ssgBot) !== false) {
                return false; // Allow our own bot
            }
        }

        // Initialize bot score for multi-factor analysis
        /** @var int $botScore Cumulative bot detection score */
        $botScore = 0;

        // 1. Check if IP is in known bot ranges - IMMEDIATE BOT FLAG (fastest + most reliable)
        if ($this->botRangeFetcher->isIpInBotRanges($remoteAddr)) {
            return true;
        }

        // 2. Forward-confirmed Reverse DNS (expensive but valuable)
        if (!empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            $ptr = $this->dnsResolver->forwardConfirmedReverseDns($remoteAddr);
            $botScore += $this->dnsResolver->getBotDomainScore($ptr);
        }

        // 3. User Agent Keyword Check (fast but less reliable)
        $botScore += $this->keywordDetector->getKeywordScore($userAgent);

        // 4. Header-suspicion scoring (now includes WebDriver checks)
        $botScore += $this->headerDetector->getSuspiciousHeaderScore($userAgent, $acceptLanguage, $referer);

        // 5. Additional header heuristics and short/empty UA checks
        $botScore += $this->headerDetector->getAdditionalHeaderScore($userAgent, $acceptHeader, $acceptEncoding);

        // 6. Lightweight recent-request rate check (per-IP, short window)
        $botScore += $this->rateLimiter->getRateScore($remoteAddr);

        // 7. Custom Hook for Additional Checks
        /** @var int $botScore Modified score after applying custom filters */
        $botScore = apply_filters('ssg_bot_score', $botScore, $userAgent, $remoteAddr, $referer);

        // Log suspicious activity for debugging (score >= 4)
        if ($botScore >= 4) {
            $uaShort = $userAgent !== '' ? substr($userAgent, 0, 200) : 'empty';
            $ip = $remoteAddr !== '' ? $remoteAddr : 'unknown';
            error_log(sprintf(
                'SSG Bot Detection: Suspicious visitor (Score: %d) - UA: %s, IP: %s',
                $botScore,
                $uaShort,
                $ip
            ));
        }

        // Final decision: bot if score reaches threshold
        // Threshold: 4+ points from various detection methods
        return $botScore >= 4;
    }
}
