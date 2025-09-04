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
	 * Check if the current visitor is a bot using conservative detection.
	 *
	 * This method implements a highly conservative bot detection strategy that only
	 * relies on the most reliable methods to avoid false positives:
	 *
	 * Detection Flow:
	 * 1. Official bot IP ranges (immediate return true if matched)
	 * 2. DNS-based detection (immediate return true for high-confidence detections)
	 * 3. Everything else = human (return false)
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
		ObjectCache::set( $cacheKey, false, 86400 ); // Cache for 1 day
		return false;
	}
}
