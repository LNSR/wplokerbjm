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
     * Cache key for storing parsed bot ranges
     */
    private const BOT_RANGES_CACHE_KEY = 'ssg_bot_ip_ranges';
    private const BOT_RANGES_CACHE_TTL = 86400; // 24 hours

    /**
     * Get bot IP ranges from cache or fetch from official sources
     */
    public function getBotRanges(): array
    {
        if (!empty(self::$knownBotRanges)) {
            return self::$knownBotRanges;
        }

        // Try to get from cache first
        $cached = ObjectCache::get(self::BOT_RANGES_CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            self::$knownBotRanges = $cached;
            return $cached;
        }

        // Fetch from official sources
        $ranges = $this->fetchBotRanges();

        // Cache the results
        ObjectCache::set(self::BOT_RANGES_CACHE_KEY, $ranges, self::BOT_RANGES_CACHE_TTL);

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

        $ranges = $this->getBotRanges();
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
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
                return true;
            }
        }

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
                // Yandex lists them as: 5.45.192.0/18 5.255.192.0/18 etc.
                if (preg_match_all('/(\d+\.\d+\.\d+\.\d+\/\d+)/', $html, $matches)) {
                    $ranges = $matches[1];
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
}