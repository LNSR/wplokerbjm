<?php
namespace WPLokerBJM\Services\Utilities\SSG;
use WPLokerBJM\Core\ObjectCache;

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
		private DnsResolver $dnsResolver
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
		$cacheKey = 'ssg_is_bot_' . md5($userAgent . $acceptLanguage . $acceptHeader . $remoteAddr);
		$cachedResult = ObjectCache::get($cacheKey);
		if ($cachedResult !== false) {
			return (bool) $cachedResult;
		}

		//! Exclude our own SSG bot from being treated as a bot
		if (in_array($userAgent, self::isSsgBotGeneration(), true)) {
			ObjectCache::set($cacheKey, false, 3600); // Cache for 1 day
			return false;
		}


		// 1. Check if IP is in known bot ranges - IMMEDIATE BOT FLAG (most reliable)
		if ($this->botRangeFetcher->isIpInBotRanges($remoteAddr)) {
			error_log('[SSG BotDetection] BOT DETECTED BY IP: ' . $remoteAddr);
			ObjectCache::set($cacheKey, true, 3600); // Cache for 1 day
			return true;
		}

		// 2. Forward-confirmed Reverse DNS (highly reliable for legitimate bots)
		if (!empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
			$ptr = $this->dnsResolver->forwardConfirmedReverseDns($remoteAddr);

			// IMMEDIATE BOT FLAG for confirmed known bot PTR patterns
			if ($this->dnsResolver->isKnownBotPtr($ptr)) {
				error_log('[SSG BotDetection] BOT DETECTED BY PTR: ' . $remoteAddr . ' PTR: ' . $ptr);
				ObjectCache::set($cacheKey, true, 3600); // Cache for 1 day
				return true;
			}
		}

		// 3. Request Pattern Analysis (for bots that don't identify themselves properly)
		if ($this->analyzeRequestPatterns()) {
			error_log('[SSG BotDetection] BOT DETECTED BY REQUEST PATTERN: IP=' . $remoteAddr . ' Headers=' . json_encode([
				'accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
				'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
				'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
				'connection' => $_SERVER['HTTP_CONNECTION'] ?? ''
			]));
			ObjectCache::set($cacheKey, true, 3600); // Cache for 1 day
			return true;
		}

		ObjectCache::set($cacheKey, false, 3600); // Cache for 1 day
		return false;
	}

	/**
	 * Check if the current visitor is our SSG bot generation
	 */
	public static function isSsgBotGeneration(): array
	{
		return apply_filters('ssg_excluded_user_agents', [
			'SSG-Bot/1.0',
			'Mozilla/5.0 (compatible; SSG-Bot/1.0)',
		]);
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
		if (!empty($userAgent) && $this->isUserAgentKnownBot($userAgent)) {
			ObjectCache::set('ssg_user_agent_bot_' . md5($userAgent), true, 3600);
			return true;
		}

		// Cache pattern analysis
		$patternKey = 'ssg_request_pattern_' . md5(
			($_SERVER['HTTP_ACCEPT'] ?? '') .
			($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
			($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '') .
			($_SERVER['HTTP_CONNECTION'] ?? '') .
			($_SERVER['SERVER_PROTOCOL'] ?? '')
		);
		$cachedPattern = ObjectCache::get($patternKey);
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
			ObjectCache::set($patternKey, true, 3600);
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
					ObjectCache::set($patternKey, true, 3600);
					return true;
				}
			}
		}

		// Check for HTTP/1.0 usage (many bots still use this)
		$protocol = $_SERVER['SERVER_PROTOCOL'] ?? '';
		if ($protocol === 'HTTP/1.0') {
			ObjectCache::set($patternKey, true, 3600);
			return true;
		}

		// Check for suspicious connection headers
		$connection = strtolower($_SERVER['HTTP_CONNECTION'] ?? '');
		if ($connection === 'close' && empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
			// Bots often use "Connection: close" without accept-encoding
			ObjectCache::set($patternKey, true, 3600);
			return true;
		}


		ObjectCache::set($patternKey, false, 3600);
		return false;
	}

	/**
	 * Check whether a User Agent matches known bot UA patterns
	 * Uses cached UA lists fetched from TXT/JSON sources (see BotRangeFetcher::getBotUserAgentPatterns)
	 */
	private function isUserAgentKnownBot(string $userAgent): bool
	{
		if (empty($userAgent)) {
			return false;
		}

		$patterns = $this->botRangeFetcher->getBotUserAgentPatterns();
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
		$cacheKey = 'dns_ptr_' . $ip;
		$cachedResult = ObjectCache::get($cacheKey);
		if ($cachedResult !== false) {
			return $cachedResult === 'null' ? null : $cachedResult;
		}

		// Get PTR record with timeout handling
		$ptr = @gethostbyaddr($ip);
		if (empty($ptr) || $ptr === $ip) {
			ObjectCache::set($cacheKey, 'null', 3600); // Cache null result for 1 day
			return null;
		}

		// Resolve PTR back to IP addresses (A/AAAA) and ensure the original IP is present
		// Use @ to suppress warnings for timeout/failure cases
		$records = @dns_get_record($ptr, DNS_A + DNS_AAAA);
		if (empty($records) || !is_array($records)) {
			ObjectCache::set($cacheKey, 'null', 3600); // Cache null result for 1 day
			return null;
		}

		foreach ($records as $r) {
			if (!empty($r['ip']) && $r['ip'] === $ip) {
				ObjectCache::set($cacheKey, $ptr, 3600); // Cache successful result for 1 day
				return $ptr;
			}
			if (!empty($r['ipv6']) && $r['ipv6'] === $ip) {
				ObjectCache::set($cacheKey, $ptr, 3600); // Cache successful result for 1 day
				return $ptr;
			}
		}

		ObjectCache::set($cacheKey, 'null', 3600); // Cache null result for 1 day
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
				ObjectCache::set($cacheKey, true, 3600); // Cache for 24 hours
				return true;
			}
		}

		ObjectCache::set($cacheKey, false, 3600); // Cache for 24 hours
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

		$cacheKey = 'dns_is_known_bot_ip_' . $ip;
		$cachedResult = ObjectCache::get($cacheKey);
		if ($cachedResult !== false) {
			return (bool) $cachedResult;
		}

		$ptr = $this->forwardConfirmedReverseDns($ip);
		if ($this->isKnownBotPtr($ptr)) {
			ObjectCache::set($cacheKey, true, 3600);
			return true;
		}

		ObjectCache::set($cacheKey, false, 3600);
		return false;
	}
}
/**
 * Helper class for fetching and caching bot IP ranges
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

		// Remove duplicates and ensure we have valid ranges
		$ranges = array_unique($ranges);
		$ranges = array_filter($ranges, [$this, 'isValidCidr']);

		// Cache the results (shorter cache time if using fallbacks)
		$cacheTime = count($ranges) > 100 ? 3600 : 3600; // 1 day vs 1 hour
		ObjectCache::set($cacheKey, $ranges, expiration: $cacheTime);

		self::$knownBotRanges = $ranges;
		return $ranges;
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
		$cacheKey = 'ssg_bot_user_agents';
		$cached = ObjectCache::get($cacheKey);
		if ($cached !== false && is_array($cached)) {
			return $cached;
		}

		$patterns = $this->fetchBotUserAgentLists();
		$patterns = array_unique($patterns);

		// Cache for 1 day by default
		ObjectCache::set($cacheKey, $patterns, 3600);
		return $patterns;
	}

	/**
	 * Fetch UA lists from TXT/JSON sources and convert them into PCRE patterns.
	 */
	private function fetchBotUserAgentLists(): array
	{
		$patterns = [];

		// 1) Monperrus crawler-user-agents JSON
		try {
			$json = $this->fetchUrl('https://raw.githubusercontent.com/monperrus/crawler-user-agents/master/crawler-user-agents.json');
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
		} catch (\Exception $e) {
			error_log('[SSG BotRangeFetcher] Failed fetching crawler-user-agents JSON: ' . $e->getMessage());
		}

		// 2) MitchellKrogza 'bad-user-agents' text list (line-separated)
		try {
			$raw = $this->fetchUrl('https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-user-agents.list');
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
		} catch (\Exception $e) {
			error_log('[SSG BotRangeFetcher] Failed fetching bad-user-agents list: ' . $e->getMessage());
		}

		return array_unique(array_filter($patterns));
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
			ObjectCache::set($cacheKey, false, 3600); // Cache for 24 hours
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
				ObjectCache::set($cacheKey, true, 3600); // Cache for 24 hours
				return true;
			}
		}

		ObjectCache::set($cacheKey, false, 3600); // Cache for 24 hours
		return false;
	}

	/**
	 * Fetch bot IP ranges from official JSON sources with improved error handling
	 */
	private function fetchBotRanges(): array
	{
		$ranges = [];

		// Googlebot ranges (with retry logic)
		$googleRanges = $this->fetchWithRetry([$this, 'fetchGoogleBotRanges']);
		$ranges = array_merge($ranges, $googleRanges);

		// Bingbot ranges
		$bingRanges = $this->fetchWithRetry([$this, 'fetchBingBotRanges']);
		$ranges = array_merge($ranges, $bingRanges);

		// DuckDuckBot ranges
		$duckRanges = $this->fetchWithRetry([$this, 'fetchDuckDuckBotRanges']);
		$ranges = array_merge($ranges, $duckRanges);

		// Yandex ranges
		$yandexRanges = $this->fetchWithRetry([$this, 'fetchYandexRanges']);
		$ranges = array_merge($ranges, $yandexRanges);

		// Applebot ranges
		$appleRanges = $this->fetchWithRetry([$this, 'fetchAppleBotRanges']);
		$ranges = array_merge($ranges, $appleRanges);

		// Google special crawlers ranges (AdsBot, etc.)
		$specialRanges = $this->fetchWithRetry([$this, 'fetchGoogleSpecialCrawlersRanges']);
		$ranges = array_merge($ranges, $specialRanges);

		// Google user triggered fetchers ranges
		$userFetchersRanges = $this->fetchWithRetry([$this, 'fetchGoogleUserTriggeredFetchersRanges']);
		$ranges = array_merge($ranges, $userFetchersRanges);

		// Google user triggered fetchers Google ranges
		$userFetchersGoogleRanges = $this->fetchWithRetry([$this, 'fetchGoogleUserTriggeredFetchersGoogleRanges']);
		$ranges = array_merge($ranges, $userFetchersGoogleRanges);

		// OpenAI bot ranges
		$openAiRanges = $this->fetchWithRetry([$this, 'fetchOpenAiBotRanges']);
		$ranges = array_merge($ranges, $openAiRanges);

		// Perplexity bot ranges
		$perplexityRanges = $this->fetchWithRetry([$this, 'fetchPerplexityBotRanges']);
		$ranges = array_merge($ranges, $perplexityRanges);

		// Pinterest bot ranges
		$pinterestRanges = $this->fetchWithRetry([$this, 'fetchPinterestBotRanges']);
		$ranges = array_merge($ranges, $pinterestRanges);

		// QUIC.cloud ranges
		$quicCloudRanges = $this->fetchWithRetry([$this, 'fetchQuicCloudRanges']);
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
		$socialRanges = $this->fetchWithRetry([$this, 'fetchSocialPreviewRanges']);
		$ranges = array_merge($ranges, $socialRanges);

		// Monitoring and archive services
		$monitorRanges = $this->fetchMonitoringAndArchiveRanges();
		$ranges = array_merge($ranges, $monitorRanges);

		// Additional bot ranges from open-source databases
		$openSourceRanges = $this->fetchWithRetry([$this, 'fetchOpenSourceBotRanges']);
		$ranges = array_merge($ranges, $openSourceRanges);

		return array_unique($ranges);
	}

	/**
	 * Fetch data with retry logic to handle temporary failures
	 */
	private function fetchWithRetry(callable $fetchFunction, int $maxRetries = 2): array
	{
		$attempt = 0;
		while ($attempt < $maxRetries) {
			try {
				$result = call_user_func($fetchFunction);
				if (!empty($result)) {
					return $result;
				}
			} catch (\Exception $e) {
				error_log('[SSG BotRangeFetcher] Fetch attempt ' . ($attempt + 1) . ' failed: ' . $e->getMessage());
			}
			$attempt++;
			if ($attempt < $maxRetries) {
				sleep(1); // Brief pause between retries
			}
		}
		return [];
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
	 * @source https://www.semrush.com/bot/ (SEMrush)
	 * @source https://ahrefs.com/robot (Ahrefs)
	 * @source https://moz.com/help/mozbot (Moz)
	 */
	private function fetchSeoToolRanges(): array
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
	}    /**
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
	 * Fetch URL content with timeout and better error handling
	 */
	private function fetchUrl(string $url): ?string
	{
		$context = stream_context_create([
			'http' => [
				'timeout' => 15, // Increased timeout
				'user_agent' => 'WordPress/SSG-BotDetection',
				'method' => 'GET',
				'ignore_errors' => true, // Don't fail on HTTP errors
			],
		]);

		$content = @file_get_contents($url, false, $context);

		// Check if request was successful
		if ($content === false && isset($http_response_header)) {
			$statusLine = $http_response_header[0] ?? '';
			if (strpos($statusLine, '200') === false) {
				error_log('[SSG BotRangeFetcher] HTTP error for ' . $url . ': ' . $statusLine);
			}
		}

		return $content ?: null;
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