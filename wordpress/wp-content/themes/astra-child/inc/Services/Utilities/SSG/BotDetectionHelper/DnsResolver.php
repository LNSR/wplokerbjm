<?php

namespace AstraChild\Services\Utilities\SSG\BotDetectionHelper;

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

        // Get PTR record
        $ptr = gethostbyaddr($ip);
        if (empty($ptr) || $ptr === $ip) {
            return null;
        }

        // Resolve PTR back to IP addresses (A/AAAA) and ensure the original IP is present
        $records = dns_get_record($ptr, DNS_A + DNS_AAAA);
        if (empty($records) || !is_array($records)) {
            return null;
        }

        foreach ($records as $r) {
            if (!empty($r['ip']) && $r['ip'] === $ip) {
                return $ptr;
            }
            if (!empty($r['ipv6']) && $r['ipv6'] === $ip) {
                return $ptr;
            }
        }

        return null;
    }

    /**
     * Get the real client IP, accounting for proxies/CDNs
     */
    public function getRealIp(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (take the first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Check if a PTR record matches known bot domains and return a score
     */
    public function getBotDomainScore(?string $ptr): int
    {
        if (empty($ptr)) {
            return 0;
        }

        $botDomains = apply_filters('ssg_bot_domains', [
            // Google domains
            'googlebot.com',
            'google',
            'googleusercontent.com',
            'gae.googleusercontent.com',
            'geo.googlebot.com',

            // Microsoft/Bing domains
            'bing.com',
            'msnbot.com',
            'search.msn.com',

            // Yahoo domains
            'yahoo.com',
            'yahoo.net',

            // DuckDuckGo domains
            'duckduckgo.com',
            'duckduckbot.com',

            // Apple domains
            'applebot.apple.com',

            // Yandex domains
            'yandex.com',
            'yandex.ru',
            'yandex.net',

            // Social media domains
            'facebook.com',
            'twitter.com',
            'linkedin.com',
            'whatsapp.com',
            'pinterest.com',
            'reddit.com',

            // CDN and infrastructure domains
            'amazonaws.com',
            'cloudflare.com',
            'cloudflare.net',
            'akamaitechnologies.com',
            'akamaiedge.net',
            'edgekey.net',
            'fastly.net',
            'stackpathcdn.com',

            // Other search engines and crawlers
            'baidu.com',
            'sogou.com',
            'so.com',
            'haosou.com',
            'petalsearch.com',
            'huawei.com',

            // SEO tools and monitoring
            'ahrefs.com',
            'semrush.com',
            'majestic.com',
            'screamingfrog.co.uk',
            'sitebulb.com',
            'deepcrawl.com',
            'sistrix.com',
            'seobility.net',

            // Uptime monitoring
            'uptimerobot.com',
            'pingdom.com',
            'newrelic.com',
            'datadog.com',
            'statuscake.com',

            // Performance monitoring
            'gtmetrix.com',
            'webpagetest.org',
            'pagespeed.web.dev',
            'lighthouse.google.com',

            // AI and LLM bots
            'openai.com',
            'perplexity.ai',
            'anthropic.com',
            'claude.ai',

            // Social media crawlers
            'facebook.com',
            'meta.com',
            'twitter.com',
            'x.com',
            'linkedin.com',
            'instagram.com',
            'tiktok.com'
        ]);

        foreach ($botDomains as $domain) {
            if (stripos($ptr, $domain) !== false) {
                return 1; // Return 1 if any bot domain is found
            }
        }

        return 0;
    }

    /**
     * Check if PTR record matches specific Google crawler patterns
     */
    public function isGoogleCrawler(?string $ptr): bool
    {
        if (empty($ptr)) {
            return false;
        }

        // Common Googlebot patterns
        $googlePatterns = [
            '/^crawl-\d+-\d+-\d+-\d+\.googlebot\.com$/',
            '/^geo-crawl-\d+-\d+-\d+-\d+\.geo\.googlebot\.com$/',
            '/^rate-limited-proxy-\d+-\d+-\d+-\d+\.google\.com$/',
            '/^google-proxy-\d+-\d+-\d+-\d+\.google\.com$/',
            '/^\d+-\d+-\d+-\d+\.gae\.googleusercontent\.com$/'
        ];

        foreach ($googlePatterns as $pattern) {
            if (preg_match($pattern, $ptr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enhanced bot domain scoring with pattern matching
     */
    public function getEnhancedBotScore(?string $ptr): int
    {
        if (empty($ptr)) {
            return 0;
        }

        $score = 0;

        // Check basic domain matching
        $score += $this->getBotDomainScore($ptr);

        // Additional scoring for specific patterns
        if ($this->isGoogleCrawler($ptr)) {
            $score += 2; // Higher score for confirmed Google crawler patterns
        }

        // Check for crawler-specific subdomains
        if (preg_match('/\b(crawl|bot|spider|scraper|index|fetch)\b/i', $ptr)) {
            $score += 1;
        }

        // Check for monitoring/performance tools
        if (preg_match('/\b(monitor|check|test|audit|lighthouse|pagespeed)\b/i', $ptr)) {
            $score += 1;
        }

        return min($score, 5); // Cap at 5 to prevent over-scoring
    }

    /**
     * Identify the type of bot based on PTR record
     */
    public function identifyBotType(?string $ptr): ?string
    {
        if (empty($ptr)) {
            return null;
        }

        // Google crawlers
        if ($this->isGoogleCrawler($ptr)) {
            if (strpos($ptr, 'geo.googlebot.com') !== false) {
                return 'google_geo_crawler';
            } elseif (strpos($ptr, 'rate-limited-proxy') !== false) {
                return 'google_special_crawler';
            } elseif (strpos($ptr, 'gae.googleusercontent.com') !== false) {
                return 'google_user_fetcher';
            } else {
                return 'googlebot';
            }
        }

        // Other search engines
        if (stripos($ptr, 'bing.com') !== false || stripos($ptr, 'msnbot.com') !== false) {
            return 'bingbot';
        }
        if (stripos($ptr, 'duckduckgo.com') !== false || stripos($ptr, 'duckduckbot.com') !== false) {
            return 'duckduckbot';
        }
        if (stripos($ptr, 'applebot.apple.com') !== false) {
            return 'applebot';
        }
        if (stripos($ptr, 'yandex.com') !== false || stripos($ptr, 'yandex.ru') !== false) {
            return 'yandexbot';
        }
        if (stripos($ptr, 'baidu.com') !== false) {
            return 'baiduspider';
        }

        // SEO tools
        if (stripos($ptr, 'ahrefs.com') !== false) {
            return 'ahrefsbot';
        }
        if (stripos($ptr, 'semrush.com') !== false) {
            return 'semrushbot';
        }
        if (stripos($ptr, 'screamingfrog.co.uk') !== false) {
            return 'screamingfrog';
        }

        // AI and LLM bots
        if (stripos($ptr, 'openai.com') !== false) {
            return 'openai_bot';
        }
        if (stripos($ptr, 'perplexity.ai') !== false) {
            return 'perplexity_bot';
        }
        if (stripos($ptr, 'anthropic.com') !== false || stripos($ptr, 'claude.ai') !== false) {
            return 'anthropic_bot';
        }

        // Social media crawlers
        if (stripos($ptr, 'facebook.com') !== false || stripos($ptr, 'meta.com') !== false) {
            return 'facebook_crawler';
        }
        if (stripos($ptr, 'twitter.com') !== false || stripos($ptr, 'x.com') !== false) {
            return 'twitter_crawler';
        }
        if (stripos($ptr, 'linkedin.com') !== false) {
            return 'linkedin_crawler';
        }
        if (stripos($ptr, 'instagram.com') !== false) {
            return 'instagram_crawler';
        }
        if (stripos($ptr, 'tiktok.com') !== false) {
            return 'tiktok_crawler';
        }
        if (stripos($ptr, 'pinterest.com') !== false) {
            return 'pinterest_crawler';
        }
        if (stripos($ptr, 'reddit.com') !== false) {
            return 'reddit_crawler';
        }

        // Monitoring tools
        if (stripos($ptr, 'uptimerobot.com') !== false) {
            return 'uptimerobot';
        }
        if (stripos($ptr, 'pingdom.com') !== false) {
            return 'pingdom';
        }
        if (stripos($ptr, 'gtmetrix.com') !== false) {
            return 'gtmetrix';
        }

        // Generic bot detection
        if (preg_match('/\b(bot|crawler|spider|scraper)\b/i', $ptr)) {
            return 'generic_bot';
        }

        return null;
    }
}