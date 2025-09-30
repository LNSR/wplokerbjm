<?php
namespace AstraChild\Services\Utilities\SSG;
use AstraChild\Core\ObjectCache;

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
 * @package AstraChild\Services\Utilities\SSG
 */
class BotDetection {
	public function __construct(
		private \AstraChild\Services\Utilities\SSG\BotDetectionHelper\BotRangeFetcher $botRangeFetcher,
		private \AstraChild\Services\Utilities\SSG\BotDetectionHelper\DnsResolver $dnsResolver
	) {
	}

	/**
	 * Check if the current visitor is our SSG bot generation
	 */
	public static function isSsgBotGeneration(): array {
		return apply_filters( 'ssg_excluded_user_agents', [ 
			'SSG-Bot/1.0',
			'Mozilla/5.0 (compatible; SSG-Bot/1.0)'
		] );
	}

	/**
	 * Analyze request patterns for bot-like behavior
	 */
	private function analyzeRequestPatterns(): bool {
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
		if (!$hasAcceptLanguage) $missingHeaders++;
		if (!$hasAcceptEncoding) $missingHeaders++;
		if (!$hasConnection) $missingHeaders++;

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
	public function isBot(): bool {
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
		$cacheKey = 'ssg_is_bot_' . md5( $userAgent . $acceptLanguage . $acceptHeader . $remoteAddr );
		$cachedResult = ObjectCache::get( $cacheKey );
		if ( $cachedResult !== false ) {
			return (bool) $cachedResult;
		}

		// Exclude our own SSG bot from being treated as a bot
		if ( in_array( $userAgent, self::isSsgBotGeneration(), true ) ) {
			ObjectCache::set( $cacheKey, false, 86400 ); // Cache for 1 day
			return false; // Allow our own bot
		}


		// 1. Check if IP is in known bot ranges - IMMEDIATE BOT FLAG (most reliable)
		if ( $this->botRangeFetcher->isIpInBotRanges( $remoteAddr ) ) {
			error_log('[SSG BotDetection] BOT DETECTED BY IP: ' . $remoteAddr);
			ObjectCache::set( $cacheKey, true, 86400 ); // Cache for 1 day
			return true;
		}

		// 2. Forward-confirmed Reverse DNS (highly reliable for legitimate bots)
		if ( ! empty( $remoteAddr ) && filter_var( $remoteAddr, FILTER_VALIDATE_IP ) ) {
			$ptr = $this->dnsResolver->forwardConfirmedReverseDns( $remoteAddr );

			// IMMEDIATE BOT FLAG for confirmed known bot PTR patterns
			if ( $this->dnsResolver->isKnownBotPtr( $ptr ) ) {
				error_log('[SSG BotDetection] BOT DETECTED BY PTR: ' . $remoteAddr . ' PTR: ' . $ptr);
				ObjectCache::set( $cacheKey, true, 86400 ); // Cache for 1 day
				return true;
			}
		}

		// 3. Request Pattern Analysis (for bots that don't identify themselves properly)
		if ( $this->analyzeRequestPatterns() ) {
			error_log('[SSG BotDetection] BOT DETECTED BY REQUEST PATTERN: IP=' . $remoteAddr . ' Headers=' . json_encode([
				'accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
				'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
				'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
				'connection' => $_SERVER['HTTP_CONNECTION'] ?? ''
			]));
			ObjectCache::set( $cacheKey, true, 86400 ); // Cache for 1 day
			return true;
		}

		ObjectCache::set( $cacheKey, false, 86400 ); // Cache for 1 day
		return false;
	}
}
