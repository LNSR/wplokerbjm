<?php
namespace WPLokerBJM\Services\Utilities\SSG;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Log\Logger;

/**
 * Trait for HTTP fetching functionality
 */
trait HttpFetcher
{
	protected function getLogPrefix(): string
	{
		return '[SSG HttpFetcher]';
	}

	public function fetchUrl(string $url): ?string
	{
		if (!function_exists('curl_init')) {
			Logger::warning('HttpFetcher', $this->getLogPrefix() . ' cURL not available');
			return null;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress/SSG-BotDetection');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

		$content = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		if ($content === false || $httpCode !== 200) {
			Logger::warning('HttpFetcher', $this->getLogPrefix() . ' cURL error for ' . $url . ': ' . ($error ?: 'HTTP ' . $httpCode));
			return null;
		}

		return $content;
	}

	public function fetchMultipleUrls(array $urls): array
	{
		if (!function_exists('curl_multi_init')) {
			Logger::warning('HttpFetcher', $this->getLogPrefix() . ' cURL multi not available, falling back to sequential');
			$results = [];
			foreach ($urls as $key => $url) {
				$results[$key] = $this->fetchUrl($url);
			}
			return $results;
		}

		$mh = curl_multi_init();
		$handles = [];
		$results = [];

		foreach ($urls as $key => $url) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress/SSG-BotDetection');
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

			curl_multi_add_handle($mh, $ch);
			$handles[$key] = $ch;
		}

		$running = null;
		do {
			curl_multi_exec($mh, $running);
			curl_multi_select($mh);
		} while ($running > 0);

		foreach ($handles as $key => $ch) {
			$content = curl_multi_getcontent($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$error = curl_error($ch);

			if ($content === false || $httpCode !== 200) {
				Logger::warning('HttpFetcher', $this->getLogPrefix() . ' cURL multi error for ' . $urls[$key] . ': ' . ($error ?: 'HTTP ' . $httpCode));
				$results[$key] = null;
			} else {
				$results[$key] = $content;
			}

			curl_multi_remove_handle($mh, $ch);
		}

		curl_multi_close($mh);
		return $results;
	}
}

/**
 * BotDetection
 *
 * Conservative bot detection system for WordPress SSG serving.
 *
 * This class implements a highly conservative bot detection strategy that only
 * relies on the most reliable methods to avoid false positives. The system
 * prioritizes accuracy over comprehensive detection.
 *
 * Detection Methods (in order of execution):
 * 1. Official Bot IP Ranges - Immediate flagging (most reliable)
 * 2. Forward-confirmed Reverse DNS - High-confidence domain-based detection
 *
 *
 * @package WPLokerBJM\Services\Utilities\SSG
 */
class BotDetection
{
	public function __construct(
		private BotRangeFetcher $botRangeFetcher,
		private DnsResolver $dnsResolver,
		private UserAgentDetector $userAgentDetector
	) {
	}

	/**
	 * Check if the current visitor is a bot using enhanced conservative detection.
	 *
	 * This method implements an enhanced conservative bot detection strategy that
	 * relies on reliable methods to avoid false positives:
	 *
	 * Detection Flow:
	 * 1. Official bot IP ranges (immediate return true if matched)
	 * 2. DNS-based detection (immediate return true for high-confidence detections)
	 * 3. Request pattern analysis (behavioral detection without user agents)
	 * 4. Everything else = human (return false)
	 *
	 * @return bool True if visitor is detected as a bot, false otherwise
	 */
	public function isBot(): bool
	{
		// Extract request data from server variables
		/** @var string $userAgent Browser/client user agent string */
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

		/** @var string $acceptLanguage Accept-Language header */
		$acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

		/** @var string $acceptHeader Accept header */
		$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

		/** @var string $remoteAddr Client IP address */
		$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

		// Create cache key from key request parameters
		$cacheKey = CacheKey::SSG_IS_BOT_PREFIX . md5($userAgent . $acceptLanguage . $acceptHeader . $remoteAddr);
		$cachedResult = Cache::get($cacheKey);
		if ($cachedResult !== false) {
			return (bool) $cachedResult;
		}
		

		//! Exclude our own SSG bot from being treated as a bot
		if (SharedUtils::isSsgBotRequest()) {
			Cache::set($cacheKey, false, 3600); // Cache for 1 day
			return false;
		}


		// 1. Check if IP is in known bot ranges - IMMEDIATE BOT FLAG (most reliable)
		if ($this->botRangeFetcher->isIpInBotRanges($remoteAddr)) {
			Logger::info('BotDetection', 'BOT DETECTED BY IP: ' . $remoteAddr);
			Cache::set($cacheKey, true, 3600); // Cache for 1 day
			return true;
		}

		// 2. Forward-confirmed Reverse DNS (highly reliable for legitimate bots)
		if (!empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
			$ptr = $this->dnsResolver->forwardConfirmedReverseDns($remoteAddr);

			// IMMEDIATE BOT FLAG for confirmed known bot PTR patterns
			if ($this->dnsResolver->isKnownBotPtr($ptr)) {
				Logger::info('BotDetection', 'BOT DETECTED BY PTR: ' . $remoteAddr . ' PTR: ' . $ptr);
				Cache::set($cacheKey, true, 3600); // Cache for 1 day
				return true;
			}
		}

		// 3. Request Pattern Analysis (for bots that don't identify themselves properly)
		if ($this->analyzeRequestPatterns()) {
			Logger::info('BotDetection', 'BOT DETECTED BY REQUEST PATTERN: IP=' . $remoteAddr . ' Headers=' . json_encode([
				'accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
				'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
				'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
				'connection' => $_SERVER['HTTP_CONNECTION'] ?? ''
			]));
			Cache::set($cacheKey, true, 3600); // Cache for 1 day
			return true;
		}

		Cache::set($cacheKey, false, 3600); // Cache for 1 day
		return false;
	}

	/**
	 * Refresh bot data by clearing caches and fetching fresh data
	 * Intended to be called via cron job
	 */
	public function refreshBotData(): void
	{
		try {
			// Clear caches
			Cache::delete(CacheKey::SSG_BOT_IP_RANGES);
			Cache::delete(CacheKey::SSG_BOT_USER_AGENTS);

			$this->botRangeFetcher->getBotRanges();
			$this->userAgentDetector->getBotUserAgentPatterns();

			Logger::info('BotRangeFetcher', 'Successfully refreshed bot IP ranges via cron');
		} catch (\Exception $e) {
			Logger::error('BotRangeFetcher', 'Failed to refresh bot IP ranges: ' . $e->getMessage());
		}
	}

	/**
	 * Analyze request patterns for bot-like behavior
	 */
	private function analyzeRequestPatterns(): bool
	{
		// Quick UA checks using curated/known bot user agents lists (txt/json sources).
		// Sources: Monperrus crawler-user-agents JSON and MitchellKrogza bad-user-agents list
		// See: https://github.com/monperrus/crawler-user-agents and
		// https://github.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker

		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		if (!empty($userAgent) && $this->userAgentDetector->isKnownBot($userAgent)) {
			Cache::set(CacheKey::SSG_USER_AGENT_BOT_PREFIX . md5($userAgent), true, 3600); // Cache for 1 hour
			return true;
		}

		// Cache pattern analysis
		$patternKey = CacheKey::SSG_REQUEST_PATTERN_PREFIX . md5(
			($_SERVER['HTTP_ACCEPT'] ?? '') .
			($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
			($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '') .
			($_SERVER['HTTP_CONNECTION'] ?? '') .
			($_SERVER['SERVER_PROTOCOL'] ?? '')
		);
		$cachedPattern = Cache::get($patternKey);
		if ($cachedPattern !== false) {
			return (bool) $cachedPattern;
		}

		// Check for missing common browser headers
		$hasAcceptLanguage = !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$hasAcceptEncoding = !empty($_SERVER['HTTP_ACCEPT_ENCODING']);
		$hasAccept = !empty($_SERVER['HTTP_ACCEPT']);
		$hasConnection = !empty($_SERVER['HTTP_CONNECTION']);

		// Count missing headers (bots often don't send these)
		$missingHeaders = 0;
		if (!$hasAcceptLanguage)
			$missingHeaders++;
		if (!$hasAcceptEncoding)
			$missingHeaders++;
		if (!$hasConnection)
			$missingHeaders++;

		// If missing 2+ common browser headers, likely a bot
		if ($missingHeaders >= 2) {
			Cache::set($patternKey, true, 3600);
			return true;
		}

		// Check for suspicious accept headers
		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
		if (!empty($accept)) {
			// Bots often have very simple accept headers
			$suspiciousAcceptPatterns = [
				'/^\*\/\*$/',              // Only accepts anything
				'/^text\/html$/',           // Only accepts HTML
				'/^application\/json$/',   // Only accepts JSON
				'/^text\/plain$/',          // Only accepts plain text
			];

			foreach ($suspiciousAcceptPatterns as $pattern) {
				if (preg_match($pattern, trim($accept))) {
					Cache::set($patternKey, true, 3600);
					return true;
				}
			}
		}

		// Check for HTTP/1.0 usage (many bots still use this)
		$protocol = $_SERVER['SERVER_PROTOCOL'] ?? '';
		if ($protocol === 'HTTP/1.0') {
			Cache::set($patternKey, true, 3600);
			return true;
		}

		// Check for suspicious connection headers
		$connection = strtolower($_SERVER['HTTP_CONNECTION'] ?? '');
		if ($connection === 'close' && empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
			// Bots often use "Connection: close" without accept-encoding
			Cache::set($patternKey, true, 3600);
			return true;
		}


		Cache::set($patternKey, false, 3600);
		return false;
	}
}
/**
 * Helper class for DNS resolution and PTR record lookups
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
		$cacheKey = CacheKey::DNS_PTR_PREFIX . $ip;
		$cachedResult = Cache::get($cacheKey);
		if ($cachedResult !== false) {
			return $cachedResult === 'null' ? null : $cachedResult;
		}

		// Get PTR record with timeout handling
		$ptr = @gethostbyaddr($ip);
		if (empty($ptr) || $ptr === $ip) {
			Cache::set($cacheKey, 'null', 3600); // Cache null result for 1 day
			return null;
		}

		// Resolve PTR back to IP addresses (A/AAAA) and ensure the original IP is present
		// Use @ to suppress warnings for timeout/failure cases
		$records = @dns_get_record($ptr, DNS_A + DNS_AAAA);
		if (empty($records) || !is_array($records)) {
			Cache::set($cacheKey, 'null', 3600); // Cache null result for 1 day
			return null;
		}

		foreach ($records as $r) {
			if (!empty($r['ip']) && $r['ip'] === $ip) {
				Cache::set($cacheKey, $ptr, 3600); // Cache successful result for 1 day
				return $ptr;
			}
			if (!empty($r['ipv6']) && $r['ipv6'] === $ip) {
				Cache::set($cacheKey, $ptr, 3600); // Cache successful result for 1 day
				return $ptr;
			}
		}

		Cache::set($cacheKey, 'null', 3600); // Cache null result for 1 day
		return null;
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
		$cacheKey = CacheKey::DNS_IS_KNOWN_BOT_PREFIX . md5($ptr);
		$cachedResult = Cache::get($cacheKey);
		if ($cachedResult !== false) {
			return (bool) $cachedResult;
		}

		// Check against provider PTR suffix patterns
		$providerPatterns = $this->getProviderPtrPatterns();
		foreach ($providerPatterns as $provider => $patterns) {
			if ($this->ptrMatchesProvider($ptr, $patterns)) {
				Cache::set($cacheKey, true, 3600); // Cache for 24 hours
				return true;
			}
		}

		Cache::set($cacheKey, false, 3600); // Cache for 24 hours
		return false;
	}

	/**
	 * Combined PTR verification for an IP address.
	 * Performs forward-confirmed reverse DNS and checks whether PTR matches known
	 * provider suffix patterns. Result is cached for 24 hours.
	 *
	 * Source: provider PTR suffix patterns are defined in getProviderPtrPatterns() referencing official provider docs.
	 */
	public function isPtrKnownBotForIp(string $ip): bool
	{
		if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
			return false;
		}

		$cacheKey = CacheKey::DNS_IS_KNOWN_BOT_IP_PREFIX . $ip;
		$cachedResult = Cache::get($cacheKey);
		if ($cachedResult !== false) {
			return (bool) $cachedResult;
		}

		$ptr = $this->forwardConfirmedReverseDns($ip);
		if ($this->isKnownBotPtr($ptr)) {
			Cache::set($cacheKey, true, 3600);
			return true;
		}

		Cache::set($cacheKey, false, 3600);
		return false;
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
				'/\.amazonaws\.com$/i',
			],
			'anthropic' => [
				'/\.anthropic\.com$/i',
				'/claude/i',
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
			'cohere' => [
				'/\.cohere\.ai$/i',
				'/\.cohere\.com$/i',
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
				'/\.meta\.com$/i',
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
				'/\.googleapis\.com$/i',
			],
			'gtmetrix' => [
				'/\.gtmetrix\.com$/i',
			],
			'highwinds' => [
				'/\.highwinds\.com$/i',
			],
			'huggingface' => [
				'/\.huggingface\.co$/i',
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
			'microsoft' => [
				'/\.microsoft\.com$/i',
				'/\.msn\.com$/i',
				'/\.live\.com$/i',
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
			'openai' => [
				'/\.openai\.com$/i',
				'/\.openai\.org$/i',
				'/gptbot/i',
				'/chatgpt/i',
			],
			'perplexity' => [
				'/\.perplexity\.ai$/i',
				'/perplexitybot/i',
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
			'semrush' => [
				'/\.semrush\.com$/i',
				'/semrushbot/i',
				'/semrush/i',
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
				'/\.x\.com$/i',
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

}
/**
 * Helper class for fetching and caching bot IP ranges
 */
class BotRangeFetcher
{
	use HttpFetcher;

	protected function getLogPrefix(): string
	{
		return '[SSG BotRangeFetcher]';
	}

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

		$cacheKey = CacheKey::SSG_BOT_IP_RANGES;

		// Try to get from cache first
		$cached = Cache::get($cacheKey);
		if ($cached !== false && is_array($cached)) {
			self::$knownBotRanges = $cached;
			return $cached;
		}

		// Fetch from official sources
		$ranges = $this->fetchBotRanges();

		// Remove duplicates and ensure we have valid ranges
		$ranges = array_unique($ranges);
		$ranges = array_filter($ranges, [$this, 'isValidCidr']);

		// Cache the results (shorter cache time if using fallbacks)
		$cacheTime = count($ranges) > 100 ? 3600 : 3600; // 1 day vs 1 hour
		Cache::set($cacheKey, $ranges, expiration: $cacheTime);

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
		$cacheKey = CacheKey::SSG_IP_IN_BOT_RANGES_PREFIX . $ip;
		$cachedResult = Cache::get($cacheKey);
		if ($cachedResult !== false) {
			return (bool) $cachedResult;
		}

		$ranges = $this->getBotRanges();
		$ipLong = ip2long($ip);
		if ($ipLong === false) {
			Cache::set($cacheKey, false, 3600); // Cache for 24 hours
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
				Cache::set($cacheKey, true, 3600); // Cache for 24 hours
				return true;
			}
		}

		Cache::set($cacheKey, false, 3600); // Cache for 24 hours
		return false;
	}

	/**
	 * Fetch bot IP ranges from official JSON sources with improved error handling
	 */
	private function fetchBotRanges(): array
	{
		$ranges = [];

		// Define sources with URLs and parsing functions
		$sources = [
			// @source https://developers.google.com/search/apis/ipranges/googlebot.json
			'google' => [
				'url' => 'https://developers.google.com/search/apis/ipranges/googlebot.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://www.bing.com/toolbox/bingbot.json
			'bing' => [
				'url' => 'https://www.bing.com/toolbox/bingbot.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://duckduckgo.com/duckduckbot.json
			'duckduck' => [
				'url' => 'https://duckduckgo.com/duckduckbot.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://yandex.com/ips
			'yandex' => [
				'url' => 'https://yandex.com/ips',
				'parser' => function ($html) {
					$ranges = [];
					if ($html) {
						if (preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2})/', $html, $matches)) {
							foreach ($matches[1] as $range) {
								if ($this->isValidCidr($range)) {
									$ranges[] = $range;
								}
							}
						}
					}
					return $ranges;
				}
			],
			// @source http://search.developer.apple.com/applebot.json
			'apple' => [
				'url' => 'http://search.developer.apple.com/applebot.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://developers.google.com/static/search/apis/ipranges/special-crawlers.json
			'google_special' => [
				'url' => 'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers.json
			'google_user_fetchers' => [
				'url' => 'https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers-google.json
			'google_user_fetchers_google' => [
				'url' => 'https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers-google.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://openai.com/gptbot.json
			'openai_gpt' => [
				'url' => 'https://openai.com/gptbot.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://openai.com/chatgpt-user.json
			'openai_chatgpt' => [
				'url' => 'https://openai.com/chatgpt-user.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://openai.com/searchbot.json
			'openai_search' => [
				'url' => 'https://openai.com/searchbot.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://perplexity.ai/perplexitybot.json
			'perplexity_bot' => [
				'url' => 'https://perplexity.ai/perplexitybot.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://perplexity.ai/perplexity-user.json
			'perplexity_user' => [
				'url' => 'https://perplexity.ai/perplexity-user.json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://www.quic.cloud/ips-v4?json
			'quic_cloud' => [
				'url' => 'https://www.quic.cloud/ips-v4?json',
				'parser' => function ($json) {
					$ranges = [];
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
					return $ranges;
				}
			],
			// @source https://www.facebook.com/peering/geofeed
			'facebook' => [
				'url' => 'https://www.facebook.com/peering/geofeed',
				'parser' => function ($data) {
					$ranges = [];
					if ($data) {
						if (preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\/\d{1,2}\b/', $data, $matches)) {
							foreach (array_unique($matches[0]) as $cidr) {
								if ($this->isValidCidr($cidr)) {
									$ranges[] = $cidr;
								}
							}
						}
					}
					if (empty($ranges)) {
						$ranges = [
							'31.13.24.0/21',
							'31.13.64.0/18',
							'66.220.144.0/20',
							'69.63.176.0/21',
							'69.63.184.0/21',
							'69.63.176.0/20',
							'69.171.224.0/20',
						];
					}
					$ranges = array_merge($ranges, [
						'199.59.148.0/22',
						'199.16.156.0/22',
					]);
					return array_unique($ranges);
				}
			],
			// @source https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-ip-addresses.list
			'opensource' => [
				'url' => 'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-ip-addresses.list',
				'parser' => function ($data) {
					$ranges = [];
					if ($data) {
						$lines = explode("\n", $data);
						foreach ($lines as $line) {
							$line = trim($line);
							if (empty($line) || str_starts_with($line, '#')) {
								continue;
							}
							if (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
								$ranges[] = $line . '/32';
							}
						}
					}
					return array_unique($ranges);
				}
			],
		];

		// Extract URLs for parallel fetching
		$urls = array_column($sources, 'url', 'key');

		// Fetch all URLs in parallel
		$contents = $this->fetchMultipleUrls($urls);

		// Parse each response
		foreach ($sources as $key => $source) {
			try {
				$parsedRanges = $source['parser']($contents[$key] ?? null);
				$ranges = array_merge($ranges, $parsedRanges);
			} catch (\Exception $e) {
				Logger::error('BotRangeFetcher', 'Failed to parse ' . $key . ': ' . $e->getMessage());
			}
		}

		// Add hardcoded ranges
		$ranges = array_merge($ranges, $this->fetchPinterestBotRanges());
		$ranges = array_merge($ranges, $this->fetchBaiduRanges());
		$ranges = array_merge($ranges, $this->fetchSogouRanges());
		$ranges = array_merge($ranges, $this->fetch360SpiderRanges());
		$ranges = array_merge($ranges, $this->fetchSeoToolRanges());
		$ranges = array_merge($ranges, $this->fetchMonitoringAndArchiveRanges());

		return array_unique($ranges);
	}

	/**
	 * Fetch Pinterest bot IP ranges
	 * Pinterest uses IP range 54.236.1.0/24
	 *
	 * @source https://help.pinterest.com/en/business/article/pinterest-crawler
	 */
	public function fetchPinterestBotRanges(): array
	{
		$ranges = [];

		// Pinterest's documented IP range
		$ranges[] = '54.236.1.0/24';

		return $ranges;
	}

	/**
	 * Fetch Baidu IP ranges (China) - Baidu doesn't provide a JSON API; using official documented ranges
	 *
	 * @source https://help.baidu.com/question?prod_id=99&class=0&id=3001
	 */
	public function fetchBaiduRanges(): array
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
	public function fetchSogouRanges(): array
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
	public function fetch360SpiderRanges(): array
	{
		return [
			'42.236.99.0/24',
			'101.199.97.0/24',
		];
	}

	/**
	 * Fetch common SEO tool IP ranges (Ahrefs, SEMrush, Moz examples)
	 *
	 * @source https://www.semrush.com/bot/ (SEMrush)
	 * @source https://ahrefs.com/robot (Ahrefs)
	 * @source https://moz.com/help/mozbot (Moz)
	 */
	public function fetchSeoToolRanges(): array
	{
		return [
			// SEMrush Bot - Known IP ranges
			'85.208.96.0/24',    // SEMrush primary range
			'85.208.97.0/24',    // SEMrush secondary range
			'185.191.171.0/24',  // SEMrushBot range (confirmed from logs: 185.191.171.16, 185.191.171.19)
			'185.191.172.0/24',  // Additional SEMrushBot range
			'185.191.173.0/24',  // Additional SEMrushBot range
			'185.191.174.0/24',  // Additional SEMrushBot range
			'185.191.175.0/24',  // Additional SEMrushBot range

			// Ahrefs (sample blocks - verify against ahrefs.com current list)
			'54.36.148.0/24',
			'54.36.149.0/24',
			'54.36.150.0/24',

			// Moz (sample)
			'192.69.160.0/24',
			'192.69.161.0/24',
		];
	}

	/**
	 * Monitoring and archival services ranges
	 *
	 * @source https://uptimerobot.com/faq/#What-are-the-IP-addresses-used-by-UptimeRobot (UptimeRobot)
	 * @source https://archive.org/details/wayback-machine-crawler (Archive.org)
	 */
	public function fetchMonitoringAndArchiveRanges(): array
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
	 * Validate CIDR notation
	 */
	public function isValidCidr(string $cidr): bool
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

/**
 * Helper class for fetching and caching bot user-agent patterns
 */
class UserAgentDetector
{
	use HttpFetcher;

	protected function getLogPrefix(): string
	{
		return '[SSG UserAgentDetector]';
	}

	/**
	 * Get known bot user-agent patterns (compiled as PCRE regex strings)
	 * Fetches from JSON/TXT public sources and caches results.
	 *
	 * Sources:
	 * - Monperrus crawler-user-agents JSON: https://github.com/monperrus/crawler-user-agents
	 * - MitchellKrogza bad-user-agents list: https://github.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker
	 *
	 * Note: This method avoids request-time blocking by using the cached patterns when available.
	 */
	public function getBotUserAgentPatterns(): array
	{
		$cacheKey = CacheKey::SSG_BOT_USER_AGENTS;
		$cached = Cache::get($cacheKey);
		if ($cached !== false && is_array($cached)) {
			return $cached;
		}

		$patterns = $this->fetchBotUserAgentLists();
		$patterns = array_unique($patterns);

		// Cache for 1 day by default
		Cache::set($cacheKey, $patterns, 3600);
		return $patterns;
	}

	/**
	 * Check whether a User Agent matches known bot UA patterns
	 * Uses cached UA lists fetched from TXT/JSON sources (see getBotUserAgentPatterns)
	 */
	public function isKnownBot(string $userAgent): bool
	{
		if (empty($userAgent)) {
			return false;
		}

		$patterns = $this->getBotUserAgentPatterns();
		if (empty($patterns)) {
			return false;
		}

		foreach ($patterns as $pattern) {
			// patterns are prepared as valid PCRE regex strings
			try {
				if (@preg_match($pattern, $userAgent)) {
					return true;
				}
			} catch (\Throwable $e) {
				// ignore invalid regex from external sources; continue
				continue;
			}
		}

		return false;
	}

	/**
	 * Fetch UA lists from TXT/JSON sources and convert them into PCRE patterns.
	 */
	private function fetchBotUserAgentLists(): array
	{
		$patterns = [];

		// Define sources with URLs and parsing functions
		$sources = [
			// @source https://raw.githubusercontent.com/monperrus/crawler-user-agents/master/crawler-user-agents.json
			'monperrus' => [
				'url' => 'https://raw.githubusercontent.com/monperrus/crawler-user-agents/master/crawler-user-agents.json',
				'parser' => function ($json) {
					$patterns = [];
					if ($json) {
						$data = json_decode($json, true);
						if (is_array($data)) {
							foreach ($data as $entry) {
								if (isset($entry['pattern']) && !empty($entry['pattern'])) {
									$raw = $entry['pattern'];
									// Normalize pattern into regex; wrap in non-capturing group, case-insensitive.
									$escaped = preg_quote($raw, '/');
									$patterns[] = '/(?:' . $escaped . ')/i';
								}
								// Instances are full UA strings - turn into exact word regex
								if (isset($entry['instances']) && is_array($entry['instances'])) {
									foreach ($entry['instances'] as $instance) {
										$instance = trim($instance);
										if ($instance === '') {
											continue;
										}
										$patterns[] = '/\b' . preg_quote($instance, '/') . '\b/i';
									}
								}
							}
						}
					}
					return $patterns;
				}
			],
			// @source https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-user-agents.list
			'mitchellkrogza' => [
				'url' => 'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-user-agents.list',
				'parser' => function ($raw) {
					$patterns = [];
					if ($raw) {
						$lines = explode("\n", $raw);
						foreach ($lines as $line) {
							$line = trim($line);
							if ($line === '' || str_starts_with($line, '#')) {
								continue;
							}
							// Convert to safe regex match on word boundary
							$patterns[] = '/\b' . preg_quote($line, '/') . '\b/i';
						}
					}
					return $patterns;
				}
			],
		];

		// Extract URLs for parallel fetching (indexed array)
		$urls = array_values(array_column($sources, 'url'));

		// Fetch all URLs in parallel
		$contents = $this->fetchMultipleUrls($urls);

		// Parse each response (contents is indexed, sources is associative)
		$sourceKeys = array_keys($sources);
		foreach ($sourceKeys as $index => $key) {
			try {
				$content = $contents[$index] ?? null;
				$parsedPatterns = $sources[$key]['parser']($content);
				$patterns = array_merge($patterns, $parsedPatterns);
			} catch (\Exception $e) {
				Logger::error('UserAgentDetector', 'Failed to parse UA list ' . $key . ': ' . $e->getMessage());
			}
		}

		return array_unique(array_filter($patterns));
	}
}