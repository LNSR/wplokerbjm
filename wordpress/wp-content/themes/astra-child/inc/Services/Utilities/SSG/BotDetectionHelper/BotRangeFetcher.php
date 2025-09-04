<?php

namespace AstraChild\Services\Utilities\SSG\BotDetectionHelper;

use AstraChild\Core\ObjectCache;

/**
 * BotRangeFetcher
 *
 * Handles fetching and caching of bot IP ranges from official sources
 */
class BotRangeFetcher
{
    /**
     * Known bot IP ranges (CIDR notation) - dynamically loaded from official sources
     */
    private static array $knownBotRanges = [];

    /**
     * Get bot IP ranges from cache or fetch from official sources
     */
    public function getBotRanges(): array
    {
        if (!empty(self::$knownBotRanges)) {
            return self::$knownBotRanges;
        }

        $cacheKey = 'ssg_bot_ip_ranges';

        // Try to get from cache first
        $cached = ObjectCache::get($cacheKey);
        if ($cached !== false && is_array($cached)) {
            self::$knownBotRanges = $cached;
            return $cached;
        }

        // Fetch from official sources
        $ranges = $this->fetchBotRanges();

        // Cache the results
        ObjectCache::set($cacheKey, $ranges, expiration: 86400);

        self::$knownBotRanges = $ranges;
        return $ranges;
    }

    /**
     * Check if an IP address is in known bot IP ranges
     */
    public function isIpInBotRanges(string $ip): bool
    {
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        // Check cache first
        $cacheKey = 'ssg_ip_in_bot_ranges_' . $ip;
        $cachedResult = ObjectCache::get($cacheKey);
        if ($cachedResult !== false) {
            return (bool) $cachedResult;
        }

        $ranges = $this->getBotRanges();
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            ObjectCache::set($cacheKey, false, 86400); // Cache for 24 hours
            return false;
        }

        foreach ($ranges as $range) {
            if (strpos($range, '/') === false) {
                continue;
            }
            list($subnet, $mask) = explode('/', $range, 2);
            $subnetLong = ip2long($subnet);
            if ($subnetLong === false) {
                continue;
            }
            $mask = (int) $mask;
            if ($mask < 0 || $mask > 32) {
                continue;
            }
            $maskLong = ~((1 << (32 - $mask)) - 1);
            if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
                ObjectCache::set($cacheKey, true, 86400); // Cache for 24 hours
                return true;
            }
        }

        ObjectCache::set($cacheKey, false, 86400); // Cache for 24 hours
        return false;
    }

    /**
     * Fetch bot IP ranges from official JSON sources
     */
    private function fetchBotRanges(): array
    {
        $ranges = [];

        // Googlebot ranges
        $googleRanges = $this->fetchGoogleBotRanges();
        $ranges = array_merge($ranges, $googleRanges);

        // Bingbot ranges
        $bingRanges = $this->fetchBingBotRanges();
        $ranges = array_merge($ranges, $bingRanges);

        // DuckDuckBot ranges
        $duckRanges = $this->fetchDuckDuckBotRanges();
        $ranges = array_merge($ranges, $duckRanges);

        // Yandex ranges
        $yandexRanges = $this->fetchYandexRanges();
        $ranges = array_merge($ranges, $yandexRanges);

        // Applebot ranges
        $appleRanges = $this->fetchAppleBotRanges();
        $ranges = array_merge($ranges, $appleRanges);

        // Google special crawlers ranges (AdsBot, etc.)
        $specialRanges = $this->fetchGoogleSpecialCrawlersRanges();
        $ranges = array_merge($ranges, $specialRanges);

        // Google user triggered fetchers ranges
        $userFetchersRanges = $this->fetchGoogleUserTriggeredFetchersRanges();
        $ranges = array_merge($ranges, $userFetchersRanges);

        // Google user triggered fetchers Google ranges
        $userFetchersGoogleRanges = $this->fetchGoogleUserTriggeredFetchersGoogleRanges();
        $ranges = array_merge($ranges, $userFetchersGoogleRanges);

        // OpenAI bot ranges
        $openAiRanges = $this->fetchOpenAiBotRanges();
        $ranges = array_merge($ranges, $openAiRanges);

        // Perplexity bot ranges
        $perplexityRanges = $this->fetchPerplexityBotRanges();
        $ranges = array_merge($ranges, $perplexityRanges);

        // Pinterest bot ranges
        $pinterestRanges = $this->fetchPinterestBotRanges();
        $ranges = array_merge($ranges, $pinterestRanges);

        // Reddit bot ranges
        $redditRanges = $this->fetchRedditBotRanges();
        $ranges = array_merge($ranges, $redditRanges);

        // QUIC.cloud ranges
        $quicCloudRanges = $this->fetchQuicCloudRanges();
        $ranges = array_merge($ranges, $quicCloudRanges);

        // Baidu ranges
        $baiduRanges = $this->fetchBaiduRanges();
        $ranges = array_merge($ranges, $baiduRanges);

        // Sogou ranges
        $sogouRanges = $this->fetchSogouRanges();
        $ranges = array_merge($ranges, $sogouRanges);

        // 360Spider ranges
        $sp360Ranges = $this->fetch360SpiderRanges();
        $ranges = array_merge($ranges, $sp360Ranges);

        // SEO tool ranges (Ahrefs, SEMrush, Moz)
        $seoToolRanges = $this->fetchSeoToolRanges();
        $ranges = array_merge($ranges, $seoToolRanges);

        // Social preview crawlers (Facebook, Twitter)
        $socialRanges = $this->fetchSocialPreviewRanges();
        $ranges = array_merge($ranges, $socialRanges);

        // Monitoring and archive services
        $monitorRanges = $this->fetchMonitoringAndArchiveRanges();
        $ranges = array_merge($ranges, $monitorRanges);

        // Additional bot ranges from open-source databases
        $openSourceRanges = $this->fetchOpenSourceBotRanges();
        $ranges = array_merge($ranges, $openSourceRanges);

        return array_unique($ranges);
    }

    /**
     * Fetch Googlebot IP ranges from official JSON
     */
    private function fetchGoogleBotRanges(): array
    {
        $ranges = [];
        try {
            $json = $this->fetchUrl('https://developers.google.com/search/apis/ipranges/googlebot.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                        // Skip IPv6 for now as our matching only handles IPv4
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Googlebot ranges: ' . $e->getMessage());
        }
        return $ranges;
    }

    /**
     * Fetch Bingbot IP ranges from official JSON
     */
    private function fetchBingBotRanges(): array
    {
        $ranges = [];
        try {
            $json = $this->fetchUrl('https://www.bing.com/toolbox/bingbot.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Bingbot ranges: ' . $e->getMessage());
        }
        return $ranges;
    }

    /**
     * Fetch DuckDuckBot IP ranges from official JSON
     */
    private function fetchDuckDuckBotRanges(): array
    {
        $ranges = [];
        try {
            $json = $this->fetchUrl('https://duckduckgo.com/duckduckbot.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch DuckDuckBot ranges: ' . $e->getMessage());
        }
        return $ranges;
    }

    /**
     * Fetch Yandex IP ranges (they provide them as text, not JSON)
     */
    private function fetchYandexRanges(): array
    {
        $ranges = [];
        try {
            $html = $this->fetchUrl('https://yandex.com/ips');
            if ($html) {
                // Parse the HTML to extract IPv4 ranges
                // Yandex lists them as: 5.45.192.0/18 5.255.192.0/18 etc., but sometimes concatenated
                // Use regex to find all IP/CIDR patterns
                if (preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2})/', $html, $matches)) {
                    foreach ($matches[1] as $range) {
                        // Validate the CIDR range
                        if ($this->isValidCidr($range)) {
                            $ranges[] = $range;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Yandex ranges: ' . $e->getMessage());
        }
        return $ranges;
    }

    /**
     * Fetch Applebot IP ranges from official JSON
     */
    private function fetchAppleBotRanges(): array
    {
        $ranges = [];
        try {
            $json = $this->fetchUrl('http://search.developer.apple.com/applebot.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Applebot ranges: ' . $e->getMessage());
        }
        return $ranges;
    }

    /**
     * Fetch Google special crawlers IP ranges from official JSON (AdsBot, etc.)
     */
    private function fetchGoogleSpecialCrawlersRanges(): array
    {
        $ranges = [];
        try {
            $json = $this->fetchUrl('https://developers.google.com/static/search/apis/ipranges/special-crawlers.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                        // Skip IPv6 for now as our matching only handles IPv4
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Google special crawlers ranges: ' . $e->getMessage());
        }
        return $ranges;
    }

    /**
     * Fetch Google user triggered fetchers IP ranges from official JSON
     */
    private function fetchGoogleUserTriggeredFetchersRanges(): array
    {
        $ranges = [];
        try {
            $json = $this->fetchUrl('https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                        // Skip IPv6 for now as our matching only handles IPv4
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Google user triggered fetchers ranges: ' . $e->getMessage());
        }
        return $ranges;
    }

    /**
     * Fetch Google user triggered fetchers Google IP ranges from official JSON
     */
    private function fetchGoogleUserTriggeredFetchersGoogleRanges(): array
    {
        $ranges = [];
        try {
            $json = $this->fetchUrl('https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers-google.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                        // Skip IPv6 for now as our matching only handles IPv4
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Google user triggered fetchers Google ranges: ' . $e->getMessage());
        }
        return $ranges;
    }

    /**
     * Fetch OpenAI bot IP ranges from official JSON sources
     */
    private function fetchOpenAiBotRanges(): array
    {
        $ranges = [];

        // GPT bot ranges
        try {
            $json = $this->fetchUrl('https://openai.com/gptbot.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch OpenAI GPT bot ranges: ' . $e->getMessage());
        }

        // ChatGPT user ranges
        try {
            $json = $this->fetchUrl('https://openai.com/chatgpt-user.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch OpenAI ChatGPT user ranges: ' . $e->getMessage());
        }

        // Search bot ranges
        try {
            $json = $this->fetchUrl('https://openai.com/searchbot.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch OpenAI search bot ranges: ' . $e->getMessage());
        }

        return $ranges;
    }

    /**
     * Fetch Perplexity bot IP ranges from official JSON sources
     */
    private function fetchPerplexityBotRanges(): array
    {
        $ranges = [];

        // Perplexity bot ranges
        try {
            $json = $this->fetchUrl('https://perplexity.ai/perplexitybot.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Perplexity bot ranges: ' . $e->getMessage());
        }

        // Perplexity user ranges
        try {
            $json = $this->fetchUrl('https://perplexity.ai/perplexity-user.json');
            if ($json) {
                $data = json_decode($json, true);
                if (isset($data['prefixes'])) {
                    foreach ($data['prefixes'] as $prefix) {
                        if (isset($prefix['ipv4Prefix'])) {
                            $ranges[] = $prefix['ipv4Prefix'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Perplexity user ranges: ' . $e->getMessage());
        }

        return $ranges;
    }

    /**
     * Fetch Pinterest bot IP ranges
     * Pinterest uses IP range 54.236.1.0/24
     *
     * @source https://help.pinterest.com/en/business/article/pinterest-crawler
     */
    private function fetchPinterestBotRanges(): array
    {
        $ranges = [];

        // Pinterest's documented IP range
        $ranges[] = '54.236.1.0/24';

        return $ranges;
    }

    /**
     * Fetch Reddit bot IP ranges
     * Reddit doesn't publish official IP ranges, but uses redditbot user agent
     */
    private function fetchRedditBotRanges(): array
    {
        $ranges = [];

        // Reddit doesn't provide official IP ranges
        // We'll rely on user agent and domain detection instead
        // No IP ranges to add here

        return $ranges;
    }

    /**
     * Fetch QUIC.cloud IP ranges from official JSON
     * QUIC.cloud provides individual IPs, convert them to /32 CIDR ranges
     */
    private function fetchQuicCloudRanges(): array
    {
        $ranges = [];
        try {
            $json = $this->fetchUrl('https://www.quic.cloud/ips-v4?json');
            if ($json) {
                $ips = json_decode($json, true);
                if (is_array($ips)) {
                    foreach ($ips as $ip) {
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                            $ranges[] = $ip . '/32';
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch QUIC.cloud ranges: ' . $e->getMessage());
        }
        return $ranges;
    }

    /**
     * Fetch Baidu IP ranges (China) - Baidu doesn't provide a JSON API; using official documented ranges
     *
     * @source https://help.baidu.com/question?prod_id=99&class=0&id=3001
     */
    private function fetchBaiduRanges(): array
    {
        return [
            '123.125.71.0/24',
            '180.76.15.0/24',
            '220.181.38.0/24',
        ];
    }

    /**
     * Fetch Sogou spider IP ranges (curated examples)
     *
     * @source https://www.sogou.com/docs/help/webmasters.htm
     */
    private function fetchSogouRanges(): array
    {
        return [
            '218.30.103.0/24',
            '220.181.7.0/24',
        ];
    }

    /**
     * Fetch 360Spider ranges (curated examples)
     *
     * @source https://www.so.com/help/help_3_2.html
     */
    private function fetch360SpiderRanges(): array
    {
        return [
            '42.236.99.0/24',
            '101.199.97.0/24',
        ];
    }

    /**
     * Fetch common SEO tool IP ranges (Ahrefs, SEMrush, Moz examples)
     *
     * @source https://ahrefs.com/robot (Ahrefs)
     * @source https://www.semrush.com/bot/ (SEMrush)
     * @source https://moz.com/help/mozbot (Moz)
     */
    private function fetchSeoToolRanges(): array
    {
        return [
            // Ahrefs (sample blocks - verify against ahrefs.com current list)
            '54.36.148.0/24',
            '54.36.149.0/24',
            '54.36.150.0/24',
            // SEMrush (sample)
            '85.208.96.0/24',
            '85.208.97.0/24',
            // Moz (sample)
            '192.69.160.0/24',
            '192.69.161.0/24',
        ];
    }

    /**
     * Fetch social preview crawler IP ranges (Facebook, Twitter samples)
     *
     * @source https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/ (Meta/Facebook)
     * @source https://help.twitter.com/en/rules-and-policies/twitter-crawler (Twitter)
     */
    private function fetchSocialPreviewRanges(): array
    {
        $ranges = [];

        // Try to fetch Meta's peering/geofeed which contains authoritative network prefixes.
        // Docs: https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/
        try {
            $data = $this->fetchUrl('https://www.facebook.com/peering/geofeed');
            if ($data) {
                // Extract any IPv4 CIDR occurrences from the feed/CSV/HTML
                if (preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\/\d{1,2}\b/', $data, $matches)) {
                    foreach (array_unique($matches[0]) as $cidr) {
                        if ($this->isValidCidr($cidr)) {
                            $ranges[] = $cidr;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch Meta peering geofeed: ' . $e->getMessage());
        }

        // If fetching/parsing failed or returned nothing, fall back to curated examples
        if (empty($ranges)) {
            $ranges = [
                // Facebook / Meta (curated examples from official docs)
                '31.13.24.0/21',
                '31.13.64.0/18',
                '66.220.144.0/20',
                '69.63.176.0/21',
                '69.63.184.0/21',
                '69.63.176.0/20',
                '69.171.224.0/20',
            ];
        }

        // Twitter (examples) - keep existing samples
        $ranges = array_merge($ranges, [
            '199.59.148.0/22',
            '199.16.156.0/22',
        ]);

        return array_unique($ranges);
    }

    /**
     * Monitoring and archival services ranges
     *
     * @source https://uptimerobot.com/faq/#What-are-the-IP-addresses-used-by-UptimeRobot (UptimeRobot)
     * @source https://archive.org/details/wayback-machine-crawler (Archive.org)
     */
    private function fetchMonitoringAndArchiveRanges(): array
    {
        return [
            // UptimeRobot (examples)
            '216.144.248.0/21',
            '208.115.199.0/24',
            // Archive.org
            '207.241.224.0/20',
        ];
    }

    /**
     * Fetch additional bot IP ranges from open-source databases
     * These provide coverage for bots that don't have official APIs
     */
    private function fetchOpenSourceBotRanges(): array
    {
        $ranges = [];

        // FireHOL abusers list (contains various bot IPs)
        try {
            $fireholData = $this->fetchUrl('https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-ip-addresses.list');
            if ($fireholData) {
                $lines = explode("\n", $fireholData);
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Skip comments and empty lines
                    if (empty($line) || str_starts_with($line, '#')) {
                        continue;
                    }
                    // Convert individual IPs to /32 CIDR ranges
                    if (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $ranges[] = $line . '/32';
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to fetch FireHOL bot ranges: ' . $e->getMessage());
        }

        // Additional open-source bot databases can be added here
        // Example: AbuseIPDB, etc.

        return array_unique($ranges);
    }

    /**
     * Fetch URL content with timeout
     */
    private function fetchUrl(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10, // 10 second timeout
                'user_agent' => 'WordPress/SSG-BotDetection',
            ]
        ]);

        $content = @file_get_contents($url, false, $context);
        return $content ?: null;
    }

    /**
     * Validate CIDR notation
     */
    private function isValidCidr(string $cidr): bool
    {
        if (!preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})$/', $cidr, $matches)) {
            return false;
        }

        $ip = $matches[1];
        $mask = (int) $matches[2];

        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        // Validate mask
        if ($mask < 0 || $mask > 32) {
            return false;
        }

        return true;
    }
}