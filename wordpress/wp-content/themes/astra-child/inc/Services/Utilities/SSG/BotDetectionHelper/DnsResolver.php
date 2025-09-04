<?php

namespace AstraChild\Services\Utilities\SSG\BotDetectionHelper;

use AstraChild\Core\ObjectCache;

/**
 * DnsResolver
 *
 * Handles DNS resolution and PTR record lookups for bot detection
 */
class DnsResolver
{
    /**
     * Perform a forward-confirmed reverse DNS lookup for an IP address.
     * Returns the PTR hostname on success (forward-confirmed), or null on failure.
     */
    public function forwardConfirmedReverseDns(string $ip): ?string
    {
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Check cache first
        $cacheKey = 'dns_ptr_' . $ip;
        $cachedResult = ObjectCache::get($cacheKey);
        if ($cachedResult !== false) {
            return $cachedResult === 'null' ? null : $cachedResult;
        }

        // Get PTR record
        $ptr = gethostbyaddr($ip);
        if (empty($ptr) || $ptr === $ip) {
            ObjectCache::set($cacheKey, 'null', 86400); // Cache null result for 1 day
            return null;
        }

        // Resolve PTR back to IP addresses (A/AAAA) and ensure the original IP is present
        $records = dns_get_record($ptr, DNS_A + DNS_AAAA);
        if (empty($records) || !is_array($records)) {
            ObjectCache::set($cacheKey, 'null', 86400); // Cache null result for 1 day
            return null;
        }

        foreach ($records as $r) {
            if (!empty($r['ip']) && $r['ip'] === $ip) {
                ObjectCache::set($cacheKey, $ptr, 86400); // Cache successful result for 1 day
                return $ptr;
            }
            if (!empty($r['ipv6']) && $r['ipv6'] === $ip) {
                ObjectCache::set($cacheKey, $ptr, 86400); // Cache successful result for 1 day
                return $ptr;
            }
        }

        ObjectCache::set($cacheKey, 'null', 86400); // Cache null result for 1 day
        return null;
    }

    /**
     * Provider PTR suffix patterns used for enhanced identification
     * Key: provider name => value: array of regex patterns to match PTR hostnames
     */
    private function getProviderPtrPatterns(): array
    {
        return [
            'ahrefs' => [
                '/\.ahrefs\.com$/i',
                '/ahrefsbot/i',
            ],
            'akamai' => [
                '/\.akamai\.com$/i',
            ],
            'amazon' => [
                '/\.amazon\.com$/i',
            ],
            'apple' => [
                '/\.apple\.com$/i',
                '/applebot\.apple\.com$/i',
            ],
            'archive' => [
                '/\.archive\.org$/i',
                '/ia_archiver/i',
            ],
            'asana' => [
                '/\.asana\.com$/i',
            ],
            'baidu' => [
                '/\.baidu\.com$/i',
                '/baiduspider/i',
            ],
            'bing' => [
                '/\.search\.msn\.com$/i',
                '/\.bing\.com$/i',
                '/\.search\.bing\.com$/i',
            ],
            'blitz' => [
                '/\.blitz\.io$/i',
            ],
            'bunny' => [
                '/\.bunny\.net$/i',
            ],
            'cdn77' => [
                '/\.cdn77\.com$/i',
            ],
            'cdnetworks' => [
                '/\.cdnetworks\.com$/i',
            ],
            'chinacache' => [
                '/\.chinacache\.com$/i',
            ],
            'cloudflare' => [
                '/\.cloudflare\.com$/i',
            ],
            'cloudfront' => [
                '/\.cloudfront\.net$/i',
            ],
            'discord' => [
                '/\.discord\.com$/i',
            ],
            'duckduckgo' => [
                '/\.duckduckgo\.com$/i',
            ],
            'ebay' => [
                '/\.ebay\.com$/i',
            ],
            'edgecast' => [
                '/\.edgecast\.com$/i',
            ],
            'facebook' => [
                '/\.facebook\.com$/i',
                '/\.facebook\.net$/i',
                '/facebookexternalhit/i',
            ],
            'fastly' => [
                '/\.fastly\.com$/i',
            ],
            'feedly' => [
                '/\.feedly\.com$/i',
            ],
            'fyber' => [
                '/\.fybersearch\.com$/i',
            ],
            'getmetrix' => [
                '/\.gtmetrix\.com$/i',
            ],
            'google' => [
                '/\.googlebot\.com$/i',
                '/\.google\.com$/i',
                '/\.googleusercontent\.com$/i',
                '/\.pagespeed\.google\.com$/i',
                '/\.pagespeed\.web\.dev$/i',
                '/\.google-analytics\.com$/i',
                '/\.googlesyndication\.com$/i',
                '/\.doubleclick\.net$/i',
                '/\.googletagmanager\.com$/i',
                '/\.googletagservices\.com$/i',
                '/\.googleadservices\.com$/i',
                '/\.gstatic\.com$/i',
                '/\.googleapis\.com$/i'
            ],
            'gtmetrix' => [
                '/\.gtmetrix\.com$/i',
            ],
            'highwinds' => [
                '/\.highwinds\.com$/i',
            ],
            'imperva' => [
                '/\.imperva\.com$/i',
            ],
            'incapsula' => [
                '/\.incapsula\.com$/i',
            ],
            'instagram' => [
                '/\.instagram\.com$/i',
            ],
            'keycdn' => [
                '/\.keycdn\.com$/i',
            ],
            'limelight' => [
                '/\.limelight\.com$/i',
            ],
            'linkedin' => [
                '/\.linkedin\.com$/i',
            ],
            'loader' => [
                '/\.loader\.io$/i',
            ],
            'majestic' => [
                '/\.majestic12\.co\.uk$/i',
            ],
            'maxcdn' => [
                '/\.maxcdn\.com$/i',
            ],
            'messenger' => [
                '/\.messenger\.com$/i',
            ],
            'meta' => [
                '/\.meta\.com$/i',
            ],
            'moz' => [
                '/\.moz\.com$/i',
            ],
            'naver' => [
                '/\.naver\.com$/i',
            ],
            'oculus' => [
                '/\.oculus\.com$/i',
            ],
            'pinterest' => [
                '/\.pinterest\.com$/i',
            ],
            'pingdom' => [
                '/\.pingdom\.com$/i',
            ],
            'quiccloud' => [
                '/\.quic\.cloud$/i',
            ],
            'rankmath' => [
                '/\.rankmath\.com$/i',
                '/\.auth.rankmath\.com$/i',
            ],
            'reddit' => [
                '/\.reddit\.com$/i',
            ],
            'seoprofiler' => [
                '/\.seoprofiler\.com$/i',
            ],
            'sitespeed' => [
                '/\.sitespeed\.io$/i',
            ],
            'slack' => [
                '/\.slack\.com$/i',
            ],
            'statuscake' => [
                '/\.statuscake\.com$/i',
            ],
            'stripe' => [
                '/\.stripe\.com$/i',
            ],
            'stackpath' => [
                '/\.stackpath\.com$/i',
            ],
            'telegram' => [
                '/\.telegram\.org$/i',
            ],
            'threads' => [
                '/\.threads\.net$/i',
            ],
            'tumblr' => [
                '/\.tumblr\.com$/i',
            ],
            'twitter' => [
                '/\.twitter\.com$/i',
                '/twitterbot/i',
            ],
            'uptrends' => [
                '/\.uptrends\.com$/i',
            ],
            'uptimerobot' => [
                '/uptimerobot/i',
            ],
            'vimeo' => [
                '/\.vimeo\.com$/i',
            ],
            'whatsapp' => [
                '/\.whatsapp\.com$/i',
            ],
            'webpagetest' => [
                '/\.webpagetest\.org$/i',
            ],
            'yahoo' => [
                '/\.yahoo\.com$/i',
            ],
            'yandex' => [
                '/\.yandex\.com$/i',
                '/\.yandex\.net$/i',
                '/\.yandex\.ru$/i',
            ],
            'zemanta' => [
                '/\.zemanta\.com$/i',
            ],
            'zoominfo' => [
                '/\.zoominfo\.com$/i',
            ],
        ];
    }

    /**
     * Check whether PTR matches provider patterns
     */
    private function ptrMatchesProvider(?string $ptr, array $patterns): bool
    {
        if (empty($ptr)) {
            return false;
        }
        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $ptr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if PTR record matches known bot provider patterns (binary detection)
     */
    public function isKnownBotPtr(?string $ptr): bool
    {
        if (empty($ptr)) {
            return false;
        }

        // Check cache first
        $cacheKey = 'dns_is_known_bot_' . md5($ptr);
        $cachedResult = ObjectCache::get($cacheKey);
        if ($cachedResult !== false) {
            return (bool) $cachedResult;
        }

        // Check against provider PTR suffix patterns
        $providerPatterns = $this->getProviderPtrPatterns();
        foreach ($providerPatterns as $provider => $patterns) {
            if ($this->ptrMatchesProvider($ptr, $patterns)) {
                ObjectCache::set($cacheKey, true, 86400); // Cache for 24 hours
                return true;
            }
        }

        ObjectCache::set($cacheKey, false, 86400); // Cache for 24 hours
        return false;
    }
}