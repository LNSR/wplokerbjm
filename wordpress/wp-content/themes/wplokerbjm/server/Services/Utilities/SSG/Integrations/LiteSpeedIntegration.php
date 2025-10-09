<?php

namespace WPLokerBJM\Services\Utilities\SSG\Integrations;

/**
 * ! WARNING: DO NOT ENABLE ESI
 * ! Enabling ESI (Edge Side Includes) can cause significant issues with NONCE
 * LiteSpeed Cache Integration Utilities
 * Handles coordination between SSG system and LiteSpeed Cache
 * @source https://docs.litespeedtech.com/lscache/lscwp/api/
 */
class LiteSpeedIntegration
{
	/**
	 * Check if LiteSpeed Cache is active
	 */
	public static function isActive(): bool
	{
		// Check if plugin is active using WordPress functions
		if (function_exists('is_plugin_active')) {
			return is_plugin_active('litespeed-cache/litespeed-cache.php');
		}
		return false;
	}

	/**
	 * Check if QUIC Cloud is active (production indicator)
	 */
	public static function isQuicCloudActive(): bool
	{
		// Use the official filter hook for QUIC.cloud verification
		if (function_exists('apply_filters') && apply_filters('litespeed_is_from_cloud', false)) {
			return true;
		}

		// Check for QUIC Cloud settings using official config API
		if (function_exists('apply_filters')) {
			$quicIps = apply_filters('litespeed_conf', 'cdn-quic_cloud_ips');
			if (!empty($quicIps)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get recommended hook priorities for coordination
	 */
	public static function getHookPriorities(): array
	{
		return [
			'ssg_before_litespeed' => 3,   // Run SSG before LiteSpeed
			'ssg_after_litespeed' => 15,   // Run SSG after LiteSpeed
		];
	}

	/**
	 * Get debounce timing recommendations
	 */
	public static function getDebounceTiming(): array
	{
		$baseTimings = [
			'normal_operation' => 30,      // 30 seconds for normal operations
			'litespeed_coordination' => 60, // 60 seconds when coordinating with LiteSpeed
			'maintenance_mode' => 120,     // 2 minutes during maintenance
		];

		// Longer debounce for QUIC Cloud (production)
		if (self::isQuicCloudActive()) {
			$baseTimings['normal_operation'] = 60;      // 1 minute on QUIC Cloud
			$baseTimings['litespeed_coordination'] = 120; // 2 minutes on QUIC Cloud
			$baseTimings['maintenance_mode'] = 300;     // 5 minutes on QUIC Cloud
		}

		return $baseTimings;
	}

	/**
	 * Log LiteSpeed coordination events
	 */
	public static function logCoordination(string $event, array $context = []): void
	{
		$environment = self::getEnvironment();
		$message = "LiteSpeed-SSG Coordination [{$environment}]: {$event}";
		if (!empty($context)) {
			$message .= " - " . json_encode($context);
		}

		// Use official LiteSpeed debug API
		if (function_exists('do_action')) {
			do_action('litespeed_debug2', $message);
		} else {
			// Fallback to error_log if do_action not available
			error_log($message);
		}
	}

	/**
	 * Get the current environment based on domain and QUIC Cloud status
	 *
	 * Enhanced detection that considers multiple factors:
	 * - Domain patterns
	 * - QUIC Cloud status
	 * - Server variables
	 * - Configuration constants
	 */
	public static function getEnvironment(): string
	{
		$host = $_SERVER['HTTP_HOST'] ?? '';
		$serverName = $_SERVER['SERVER_NAME'] ?? '';

		// Check for staging subdomain patterns
		$stagingPatterns = [
			'staging.',
			'.staging',
			'-staging',
			'staging-',
			'dev.',
			'.dev',
			'-dev',
			'dev-'
		];

		foreach ($stagingPatterns as $pattern) {
			if (strpos($host, $pattern) !== false || strpos($serverName, $pattern) !== false) {
				return 'staging';
			}
		}

		// Check for localhost/development environments
		$localhost = ['localhost', '127.0.0.1', '::1', '192.168.', '10.0.', '172.'];

		foreach ($localhost as $local) {
			if (strpos($host, $local) !== false || strpos($serverName, $local) !== false) {
				return 'local';
			}
		}

		// Check for production domain with more flexible pattern matching
		$productionDomains = [
			'lowongankerjabanjarmasin.com',
			'www.lowongankerjabanjarmasin.com',
		];

		foreach ($productionDomains as $domain) {
			if (strpos($host, $domain) !== false || strpos($serverName, $domain) !== false) {
				return self::isQuicCloudActive() ? 'production-quic' : 'production';
			}
		}

		// Check for QUIC Cloud specific indicators
		if (self::isQuicCloudActive()) {
			// Additional QUIC Cloud environment detection
			if (isset($_SERVER['HTTP_X_QUIC_CLOUD'])) {
				return 'production-quic';
			}

			// Check for QUIC Cloud specific server variables
			if (isset($_SERVER['QUIC_CLOUD']) || defined('QUIC_CLOUD_ACTIVE')) {
				return 'production-quic';
			}
		}

		// Check for environment-specific constants
		if (defined('WP_ENVIRONMENT_TYPE')) {
			$envType = WP_ENVIRONMENT_TYPE;
			if (in_array($envType, ['production', 'staging', 'development', 'local'])) {
				if ($envType === 'production' && self::isQuicCloudActive()) {
					return 'production-quic';
				}
				return $envType;
			}
		}

		// Fallback with more detailed logging
		$fallbackEnv = self::isQuicCloudActive() ? 'production-quic' : 'staging';

		self::logCoordination('Environment detection fallback used', [
			'host' => $host,
			'server_name' => $serverName,
			'quic_active' => self::isQuicCloudActive(),
			'fallback_env' => $fallbackEnv,
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
		]);

		return $fallbackEnv;
	}
}