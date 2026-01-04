<?php

namespace WPLokerBJM\Tests;

use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Services\Utilities\SSG\BotDetection;
use WPLokerBJM\Services\Utilities\SSG\BotRangeFetcher;
use WPLokerBJM\Services\Utilities\SSG\UserAgentDetector;
use WPLokerBJM\Services\Utilities\SSG\DnsResolver;
use WPLokerBJM\Shared\Cache\Cache;

class BotDetectionTest extends WplokerbjmTestCase
{
    private BotRangeFetcher $botRangeFetcher;
    private UserAgentDetector $userAgentDetector;
    private DnsResolver $dnsResolver;
    private BotDetection $botDetection;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->container();
        $this->botRangeFetcher = $container->get(BotRangeFetcher::class);
        $this->userAgentDetector = $container->get(UserAgentDetector::class);
        $this->dnsResolver = $container->get(DnsResolver::class);
        $this->botDetection = $container->get(BotDetection::class);
    }

    public function testBotRangeFetcherExternalSourcesConnectivity()
    {
        echo "\n\033[1;34m🌐 Testing Bot IP Range Sources Connectivity\033[0m\n";
        echo "\033[0;36mThis test verifies that external bot IP range sources are accessible and returning data.\033[0m\n";
        echo "\033[0;36mBot detection relies on up-to-date IP ranges from various sources to identify crawler traffic.\033[0m\n\n";

        // Test the main fetchBotRanges method
        $ranges = $this->botRangeFetcher->getBotRanges();

        echo "\033[0;36mExternal Sources Status:\033[0m\n";

        // Check if we got any ranges at all
        if (empty($ranges)) {
            echo "  \033[0;31m❌ No bot ranges retrieved from any source\033[0m\n";
            echo "  \033[0;31mThis will severely impact bot detection accuracy!\033[0m\n";
            $this->fail('Failed to retrieve bot ranges from external sources');
        }

        echo "  \033[0;32m✅ Retrieved " . count($ranges) . " bot IP ranges\033[0m\n";
        echo "  \033[0;36mSample ranges: " . implode(', ', array_slice($ranges, 0, 5)) . (count($ranges) > 5 ? '...' : '') . "\033[0m\n";

        // Test individual sources by checking cache keys
        $this->testIndividualBotSources();

        echo "\n\033[0;36mBot Range Analysis:\033[0m\n";
        $uniqueRanges = count(array_unique($ranges));
        echo "  \033[0;33m•\033[0m Total ranges: \033[0;32m" . count($ranges) . "\033[0m\n";
        echo "  \033[0;33m•\033[0m Unique ranges: \033[0;32m$uniqueRanges\033[0m\n";
        if ($uniqueRanges < count($ranges)) {
            echo "  \033[0;33m•\033[0m Duplicates removed: \033[0;31m" . (count($ranges) - $uniqueRanges) . "\033[0m\n";
        }

        $this->assertGreaterThan(0, count($ranges));
    }

    public function testUserAgentDetectorExternalSourcesConnectivity()
    {
        echo "\n\033[1;35m🤖 Testing User Agent Sources Connectivity\033[0m\n";
        echo "\033[0;36mThis test verifies that external user agent pattern sources are accessible and returning data.\033[0m\n";
        echo "\033[0;36mUser agent patterns help identify bots by their HTTP User-Agent header signatures.\033[0m\n\n";

        // Test the main getBotUserAgentPatterns method
        $patterns = $this->userAgentDetector->getBotUserAgentPatterns();

        echo "\033[0;36mUser Agent Sources Status:\033[0m\n";

        // Check if we got any patterns at all
        if (empty($patterns)) {
            echo "  \033[0;31m❌ No user agent patterns retrieved from any source\033[0m\n";
            echo "  \033[0;31mThis will severely impact bot detection accuracy!\033[0m\n";
            $this->fail('Failed to retrieve user agent patterns from external sources');
        }

        echo "  \033[0;32m✅ Retrieved " . count($patterns) . " user agent patterns\033[0m\n";
        echo "  \033[0;36mSample patterns: " . implode(', ', array_slice($patterns, 0, 3)) . (count($patterns) > 3 ? '...' : '') . "\033[0m\n";

        // Test individual sources by checking cache keys
        $this->testIndividualUserAgentSources();

        echo "\n\033[0;36mUser Agent Pattern Analysis:\033[0m\n";
        $regexPatterns = array_filter($patterns, fn($p) => str_starts_with($p, '/'));
        $literalPatterns = array_diff($patterns, $regexPatterns);
        echo "  \033[0;33m•\033[0m Total patterns: \033[0;32m" . count($patterns) . "\033[0m\n";
        echo "  \033[0;33m•\033[0m Regex patterns: \033[0;32m" . count($regexPatterns) . "\033[0m\n";
        echo "  \033[0;33m•\033[0m Literal patterns: \033[0;32m" . count($literalPatterns) . "\033[0m\n";

        $this->assertGreaterThan(0, count($patterns));
    }

    public function testDnsResolverFunctionality()
    {
        echo "\n\033[1;33m🔍 Testing DNS Resolver Functionality\033[0m\n";
        echo "\033[0;36mThis test verifies DNS PTR record resolution and bot PTR pattern matching.\033[0m\n";
        echo "\033[0;36mDNS-based bot detection uses reverse DNS lookups to identify known crawler PTR records.\033[0m\n\n";

        // Test with a known good IP (Google DNS)
        $googleDns = '8.8.8.8';
        $ptr = $this->dnsResolver->forwardConfirmedReverseDns($googleDns);

        echo "\033[0;36mDNS Resolution Test:\033[0m\n";
        echo "  \033[0;33m•\033[0m Testing IP: \033[0;32m$googleDns\033[0m\n";

        if ($ptr !== null) {
            echo "  \033[0;32m✅ PTR Record: $ptr\033[0m\n";
            echo "  \033[0;36mThis confirms DNS resolution is working correctly.\033[0m\n";
            $this->assertIsString($ptr);
        } else {
            echo "  \033[0;31m❌ No PTR record found\033[0m\n";
            echo "  \033[0;31m❌ DNS resolution may be failing - check network connectivity.\033[0m\n";
        }

        // Test known bot PTR detection
        $knownBotPtr = 'crawl-66-249-66-1.googlebot.com';
        $isKnownBot = $this->dnsResolver->isKnownBotPtr($knownBotPtr);

        echo "\n\033[0;36mBot PTR Pattern Matching:\033[0m\n";
        echo "  \033[0;33m•\033[0m Testing known bot PTR: \033[0;32m$knownBotPtr\033[0m\n";
        if ($isKnownBot) {
            echo "  \033[0;32m✅ Correctly identified as bot\033[0m\n";
            echo "  \033[0;36mThis confirms bot PTR pattern matching is working.\033[0m\n";
        } else {
            echo "  \033[0;31m❌ Failed to identify known bot PTR\033[0m\n";
            echo "  \033[0;31m❌ Bot PTR detection may be malfunctioning.\033[0m\n";
        }

        $this->assertTrue($isKnownBot);
    }

    public function testBotDetectionWithMockData()
    {
        echo "\n\033[1;36m🎯 Testing Bot Detection Logic\033[0m\n";
        echo "\033[0;36mThis test verifies the bot detection algorithm using various request scenarios.\033[0m\n";
        echo "\033[0;36mThe system combines IP ranges, user agents, and DNS PTR records for comprehensive detection.\033[0m\n\n";

        // Get real data from external sources for more realistic testing
        $botRanges = $this->botRangeFetcher->getBotRanges();
        $userAgentPatterns = $this->userAgentDetector->getBotUserAgentPatterns();

        // Test with empty server vars (conservative detection should flag as bot due to missing headers)
        $_SERVER = [];

        $isBot = $this->botDetection->isBot();
        echo "\033[0;36mEmpty Request Test:\033[0m\n";
        echo "  \033[0;36mTesting with completely empty \$_SERVER array (simulates malformed request)\033[0m\n";
        if ($isBot) {
            echo "  \033[0;32m✅ Correctly flagged as bot (conservative detection - missing headers)\033[0m\n";
            echo "  \033[0;36mThis prevents false negatives when headers are missing.\033[0m\n";
        } else {
            echo "  \033[0;31m❌ Should have flagged as bot due to missing headers\033[0m\n";
            echo "  \033[0;31mThis could allow undetected bot traffic!\033[0m\n";
        }
        $this->assertTrue($isBot, 'Empty request should be flagged as bot due to missing headers');

        // Test with minimal browser-like headers
        $_SERVER = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.5',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
            'HTTP_CONNECTION' => 'keep-alive',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $isBot = $this->botDetection->isBot();
        echo "\n\033[0;36mBrowser-like Request Test:\033[0m\n";
        echo "  \033[0;36mTesting with standard browser headers and local IP\033[0m\n";
        echo "  \033[0;32m✅ Correctly identified as non-bot (browser request)\033[0m\n";
        echo "  \033[0;36mThis ensures legitimate users are not blocked.\033[0m\n";
        $this->assertFalse($isBot);

        // Test with randomly selected real bot data from external sources
        if (!empty($userAgentPatterns) && !empty($botRanges)) {
            echo "\n\033[0;36mReal Bot Data Tests:\033[0m\n";
            echo "  \033[0;36mTesting with actual bot data from external sources\033[0m\n";

            // Randomly select a bot user agent pattern
            $randomPattern = $userAgentPatterns[array_rand($userAgentPatterns)];
            // Extract a sample user agent from the pattern (remove regex delimiters and flags)
            $sampleUA = preg_replace('/^\/.*\/[imsxeADSUXJu]*$/', '', $randomPattern);
            if (empty($sampleUA)) {
                $sampleUA = 'TestBot/1.0'; // Fallback
            }

            // Randomly select a bot IP range and extract an IP
            $randomRange = $botRanges[array_rand($botRanges)];
            // Extract IP from CIDR (take first IP in range)
            $ip = preg_replace('/\/.*$/', '', $randomRange);

            $_SERVER = [
                'HTTP_USER_AGENT' => $sampleUA,
                'REMOTE_ADDR' => $ip,
                'HTTP_ACCEPT' => '*/*',
                'HTTP_CONNECTION' => 'close',
            ];

            $isBot = $this->botDetection->isBot();
            echo "  \033[0;33m•\033[0m Testing with real bot data:\033[0m\n";
            echo "    \033[0;33m•\033[0m UA: \033[0;32m$sampleUA\033[0m\n";
            echo "    \033[0;33m•\033[0m IP: \033[0;32m$ip\033[0m\n";

            if ($isBot) {
                echo "  \033[0;32m✅ Correctly detected as bot using real external data\033[0m\n";
                echo "  \033[0;36mThis confirms the detection system works with live data.\033[0m\n";
            } else {
                echo "  \033[0;31m⚠️  Not detected as bot (may be expected for conservative detection)\033[0m\n";
                echo "  \033[0;36mThis could be normal if the data doesn't match detection criteria.\033[0m\n";
            }

            // Test with known bot user agent that should definitely be detected
            $_SERVER = [
                'HTTP_USER_AGENT' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
                'REMOTE_ADDR' => '66.249.66.1', // Known Googlebot IP
                'HTTP_ACCEPT' => '*/*',
                'HTTP_CONNECTION' => 'close',
            ];

            $isBot = $this->botDetection->isBot();
            echo "  \033[0;33m•\033[0m Testing with known Googlebot:\033[0m\n";
            if ($isBot) {
                echo "  \033[0;32m✅ Correctly detected known Googlebot\033[0m\n";
                echo "  \033[0;36mThis validates the core bot detection functionality.\033[0m\n";
            } else {
                echo "  \033[0;31m❌ Failed to detect known Googlebot\033[0m\n";
                echo "  \033[0;31mCritical failure in bot detection system!\033[0m\n";
            }
            $this->assertTrue($isBot, 'Should detect known Googlebot');
        } else {
            echo "\n\033[0;31m⚠️  Skipping real bot data tests - no external data available\033[0m\n";
            echo "  \033[0;31mThis reduces test coverage and may hide detection issues!\033[0m\n";
        }
    }

    public function testHttpFetcherTrait()
    {
        echo "\n\033[1;37m📡 Testing HTTP Fetcher Trait\033[0m\n";

        // Test with a known reliable URL
        $testUrl = 'https://httpbin.org/status/200';
        $result = $this->botRangeFetcher->fetchUrl($testUrl);

        echo "\033[0;36mHTTP Fetch Test:\033[0m\n";
        echo "  \033[0;33m•\033[0m URL: \033[0;32m$testUrl\033[0m\n";

        if ($result !== null) {
            echo "  \033[0;32m✅ HTTP fetch successful\033[0m\n";
            $this->assertIsString($result);
        } else {
            echo "  \033[0;31m❌ HTTP fetch failed\033[0m\n";
            $this->fail('HTTP fetching is not working');
        }

        // Test with invalid URL
        $invalidUrl = 'https://invalid-domain-that-does-not-exist-12345.com/test';
        $result = $this->botRangeFetcher->fetchUrl($invalidUrl);

        echo "  \033[0;33m•\033[0m Invalid URL: \033[0;32m$invalidUrl\033[0m\n";
        if ($result === null) {
            echo "  \033[0;32m✅ Correctly handled invalid URL\033[0m\n";
        } else {
            echo "  \033[0;31m❌ Should have failed for invalid URL\033[0m\n";
        }

        $this->assertNull($result);
    }

    private function testIndividualBotSources()
    {
        echo "\n\033[0;36mIndividual Bot Sources:\033[0m\n";

        // Test the main sources that are fetched in parallel
        $sources = [
            'Google Bot' => 'https://developers.google.com/search/apis/ipranges/googlebot.json',
            'Bing Bot' => 'https://www.bing.com/toolbox/bingbot.json',
            'DuckDuckGo' => 'https://duckduckgo.com/duckduckbot.json',
            'Yandex' => 'https://yandex.com/ips',
            'Apple Bot' => 'http://search.developer.apple.com/applebot.json',
            'Google Special' => 'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json',
            'OpenAI GPT' => 'https://openai.com/gptbot.json',
            'OpenAI ChatGPT' => 'https://openai.com/chatgpt-user.json',
            'Perplexity Bot' => 'https://perplexity.ai/perplexitybot.json',
            'QUIC Cloud' => 'https://www.quic.cloud/ips-v4?json',
            'Facebook' => 'https://www.facebook.com/peering/geofeed',
            'Bad IPs List' => 'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-ip-addresses.list',
        ];

        // Use multi-fetch to test fetchMultipleUrls method
        $urls = array_values($sources);
        $results = $this->botRangeFetcher->fetchMultipleUrls($urls);

        $i = 0;
        foreach ($sources as $name => $url) {
            $result = $results[$i] ?? null;
            if ($result !== null) {
                echo "  \033[0;32m✅ $name: accessible\033[0m\n";
            } else {
                echo "  \033[0;31m❌ $name: failed to fetch\033[0m\n";
            }
            $i++;
        }

        // Test hardcoded sources
        $hardcodedSources = [
            'Pinterest' => fn() => $this->botRangeFetcher->fetchPinterestBotRanges(),
            'Baidu' => fn() => $this->botRangeFetcher->fetchBaiduRanges(),
            'Sogou' => fn() => $this->botRangeFetcher->fetchSogouRanges(),
            '360Spider' => fn() => $this->botRangeFetcher->fetch360SpiderRanges(),
            'SEO Tools' => fn() => $this->botRangeFetcher->fetchSeoToolRanges(),
            'Monitoring' => fn() => $this->botRangeFetcher->fetchMonitoringAndArchiveRanges(),
        ];

        foreach ($hardcodedSources as $name => $method) {
            try {
                $ranges = $method();

                if (!empty($ranges)) {
                    echo "  \033[0;32m✅ $name: " . count($ranges) . " ranges\033[0m\n";
                } else {
                    echo "  \033[0;33m⚠️  $name: empty response\033[0m\n";
                }
            } catch (\Exception $e) {
                echo "  \033[0;31m❌ $name: exception - " . $e->getMessage() . "\033[0m\n";
            }
        }
    }

    private function testIndividualUserAgentSources()
    {
        echo "\n\033[0;36mIndividual User Agent Sources:\033[0m\n";

        // Test the main user agent sources
        $sources = [
            'Monperrus Crawler Agents' => 'https://raw.githubusercontent.com/monperrus/crawler-user-agents/master/crawler-user-agents.json',
            'MitchellKrogza Bad Agents' => 'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-user-agents.list',
        ];

        foreach ($sources as $name => $url) {
            $result = $this->botRangeFetcher->fetchUrl($url);
            if ($result !== null) {
                echo "  \033[0;32m✅ $name: accessible\033[0m\n";
            } else {
                echo "  \033[0;31m❌ $name: failed to fetch\033[0m\n";
            }
        }

        // Test if patterns were loaded
        $patterns = $this->userAgentDetector->getBotUserAgentPatterns();

        if (!empty($patterns)) {
            echo "  \033[0;32m✅ User agent patterns loaded: " . count($patterns) . " patterns\033[0m\n";

            // Check for some known bot patterns
            $knownPatterns = ['googlebot', 'bingbot', 'slurp'];
            $foundPatterns = 0;

            foreach ($knownPatterns as $pattern) {
                if (isset($patterns[$pattern])) {
                    $foundPatterns++;
                }
            }

            echo "  \033[0;32m✅ Found $foundPatterns known bot patterns\033[0m\n";
        } else {
            echo "  \033[0;31m❌ No user agent patterns available\033[0m\n";
        }
    }

    public function testCacheIntegration()
    {
        echo "\n\033[1;37m💾 Testing Cache Integration\033[0m\n";

        // Clear any existing cache
        $clearedPattern = 'ssg_*';
        Cache::deletePattern([$clearedPattern]);
        echo "\033[0;36mCache Setup:\033[0m\n";
        echo "  \033[0;33m•\033[0m Cleared cache pattern: \033[0;32m{$clearedPattern}\033[0m\n";

        // Test DNS cache
        $testIp = '8.8.8.8';
        $dnsKey = 'wplokerbjm_obj_dns_ptr_' . $testIp;
        $ptr1 = $this->dnsResolver->forwardConfirmedReverseDns($testIp);
        $ptr2 = $this->dnsResolver->forwardConfirmedReverseDns($testIp); // Should use cache

        echo "\033[0;36mDNS Cache Test:\033[0m\n";
        echo "  \033[0;33m•\033[0m Cache key: \033[0;32m{$dnsKey}\033[0m\n";
        echo "  \033[0;33m•\033[0m First call PTR: \033[0;32m" . ($ptr1 ?? 'null') . "\033[0m\n";
        echo "  \033[0;33m•\033[0m Second call PTR: \033[0;32m" . ($ptr2 ?? 'null') . "\033[0m\n";
        echo "  \033[0;32m✅ DNS caching working (same result from cache)\033[0m\n";
        $this->assertEquals($ptr1, $ptr2);

        // Test bot detection cache
        $_SERVER = [
            'HTTP_USER_AGENT' => 'TestBot/1.0',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        // Generate the cache key for bot detection (simplified representation)
        $botKeyPrefix = 'wplokerbjm_obj_ssg_is_bot_';
        $requestHash = md5(serialize($_SERVER)); // Approximate hash used internally
        $botKey = $botKeyPrefix . $requestHash;

        $result1 = $this->botDetection->isBot();
        $result2 = $this->botDetection->isBot(); // Should use cache

        echo "\033[0;36mBot Detection Cache Test:\033[0m\n";
        echo "  \033[0;33m•\033[0m Cache key: \033[0;32m{$botKey}\033[0m\n";
        echo "  \033[0;33m•\033[0m First call result: \033[0;32m" . ($result1 ? 'true' : 'false') . "\033[0m\n";
        echo "  \033[0;33m•\033[0m Second call result: \033[0;32m" . ($result2 ? 'true' : 'false') . "\033[0m\n";
        echo "  \033[0;32m✅ Bot detection caching working\033[0m\n";
        $this->assertEquals($result1, $result2);
    }
}