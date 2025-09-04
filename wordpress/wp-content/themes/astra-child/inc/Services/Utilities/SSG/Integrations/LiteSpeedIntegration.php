<?php

namespace AstraChild\Services\Utilities\SSG\Integrations;

/**
 * LiteSpeed Cache Integration Utilities
 * ! Do not enable ESI which causing issues with Admin Bar and Login
 * Handles coordination between SSG system and LiteSpeed Cache plugin
 * 
 * Key insight: Production uses QUIC Cloud which sends different headers,
 * while staging uses local LiteSpeed without QUIC Cloud
 */
class LiteSpeedIntegration
{
	/**
	 * Check if LiteSpeed Cache is active
	 */
	public static function isActive(): bool
	{
		return defined('LSCWP_DIR') ||
			class_exists('LiteSpeed\Core') ||
			function_exists('litespeed_purge_post') ||
			defined('LITESPEED_SERVER_TYPE');
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

		// Check for LSCWP QUIC Cloud settings
		if (defined('LSCWP_QUIC_CLOUD_SERVER') && LSCWP_QUIC_CLOUD_SERVER) {
			return true;
		}

		// Check LiteSpeed Cache options for QUIC Cloud
		if (function_exists('get_option')) {
			$lscache_conf = get_option('litespeed-cache-conf');
			if (is_array($lscache_conf) && !empty($lscache_conf['cdn-quic_cloud_ips'])) {
				return true;
			}
		}

		return false;
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
		$requestUri = $_SERVER['REQUEST_URI'] ?? '';

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

	/**
	 * Check if current request is a LiteSpeed cache operation
	 */
	public static function isCacheOperation(): bool
	{
		// Check request parameters for actual cache operations
		if (
			isset($_REQUEST['litespeed_purge']) ||
			isset($_REQUEST['litespeed_action']) ||
			isset($_REQUEST['litespeed_ccss']) ||
			isset($_REQUEST['litespeed_purge_all']) ||
			isset($_REQUEST['lscache_purge'])
		) {
			return true;
		}

		// Check user agent for actual LiteSpeed operations (not just presence)
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			$userAgent = $_SERVER['HTTP_USER_AGENT'];
			// Only consider actual LiteSpeed operations, not just the server
			if (
				strpos($userAgent, 'LiteSpeed/Crawler') !== false ||
				strpos($userAgent, 'LSCache/Crawler') !== false ||
				strpos($userAgent, 'LiteSpeed/Purge') !== false ||
				strpos($userAgent, 'QUIC.cloud') !== false
			) {
				return true;
			}
		}

		// Check for active purge operations via constants
		if (defined('LITESPEED_PURGE_ACTIVE') && LITESPEED_PURGE_ACTIVE) {
			return true;
		}

		// Check if we're in a LiteSpeed admin action
		if (
			is_admin() && isset($_REQUEST['action']) &&
			strpos($_REQUEST['action'], 'litespeed') !== false
		) {
			return true;
		}

		// QUIC Cloud specific detection (production)
		if (self::isQuicCloudActive()) {
			// On QUIC Cloud, check for specific cache headers
			if (
				isset($_SERVER['HTTP_X_LSCACHE_VARY']) ||
				isset($_SERVER['HTTP_X_LSCACHE_CONTROL']) ||
				isset($_SERVER['HTTP_X_LSCACHE_TAG'])
			) {
				return true;
			}

			// QUIC Cloud maintenance mode
			if (isset($_SERVER['HTTP_X_QUIC_MAINTENANCE'])) {
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
			'ssg_before_litespeed' => 5,   // Run SSG before LiteSpeed
			'litespeed_default' => 10,     // LiteSpeed default priority
			'ssg_after_litespeed' => 15,   // Run SSG after LiteSpeed
			'ssg_cleanup' => 20,           // Cleanup operations
		];
	}

	/**
	 * Check if we should skip SSG operations during LiteSpeed maintenance
	 *
	 * Enhanced detection for various maintenance scenarios including:
	 * - LiteSpeed purge operations
	 * - QUIC Cloud maintenance modes
	 * - Preload operations
	 * - Configuration updates
	 */
	public static function shouldSkipDuringMaintenance(): bool
	{
		// Skip during LiteSpeed's purge all operations
		if (isset($_REQUEST['litespeed_purge_all'])) {
			self::logCoordination('Skipping SSG during LiteSpeed purge all operation');
			return true;
		}

		// Skip during LiteSpeed's preload operations
		if (defined('LITESPEED_PRELOAD_ACTIVE') && LITESPEED_PRELOAD_ACTIVE) {
			self::logCoordination('Skipping SSG during LiteSpeed preload operation');
			return true;
		}

		// Skip during active LiteSpeed optimization
		if (defined('LITESPEED_OPTIMIZE_ACTIVE') && LITESPEED_OPTIMIZE_ACTIVE) {
			self::logCoordination('Skipping SSG during LiteSpeed optimization');
			return true;
		}

		// QUIC Cloud specific maintenance detection
		if (self::isQuicCloudActive()) {
			// QUIC Cloud maintenance mode
			if (isset($_SERVER['HTTP_X_QUIC_MAINTENANCE'])) {
				self::logCoordination('Skipping SSG during QUIC Cloud maintenance');
				return true;
			}

			// QUIC Cloud configuration updates
			if (isset($_SERVER['HTTP_X_QUIC_CONFIG_UPDATE'])) {
				self::logCoordination('Skipping SSG during QUIC Cloud config update');
				return true;
			}

			// QUIC Cloud service updates
			if (isset($_SERVER['HTTP_X_QUIC_SERVICE_UPDATE'])) {
				self::logCoordination('Skipping SSG during QUIC Cloud service update');
				return true;
			}
		}

		// Check for LiteSpeed admin operations that might affect caching
		if (is_admin() && isset($_REQUEST['action'])) {
			$action = $_REQUEST['action'];
			$maintenanceActions = [
				'litespeed_purge',
				'litespeed_purge_all',
				'litespeed_clean',
				'litespeed_clear',
				'litespeed_reset'
			];

			if (in_array($action, $maintenanceActions) || strpos($action, 'litespeed') === 0) {
				self::logCoordination('Skipping SSG during LiteSpeed admin action', ['action' => $action]);
				return true;
			}
		}

		// Check for WordPress maintenance mode
		if (defined('WP_MAINTENANCE_MODE') && WP_MAINTENANCE_MODE) {
			self::logCoordination('Skipping SSG during WordPress maintenance mode');
			return true;
		}

		// Check for custom maintenance indicators
		if (get_option('ssg_maintenance_mode', false)) {
			self::logCoordination('Skipping SSG during custom SSG maintenance mode');
			return true;
		}

		return false;
	}

	/**
	 * Detect if the current request is performing ESI (Edge Side Includes) processing.
	 *
	 * We treat a request as ESI if common surrogate/ESI headers or query params are present.
	 * This is conservative: it's better to skip SSG during ESI to avoid returning a full
	 * static page for a request that expects fragment processing.
	 */
	public static function isEsiRequest(): bool
	{
		// Standard surrogate headers used by some caches (Varnish, Fastly, etc.)
		if (isset($_SERVER['HTTP_SURROGATE_CONTROL']) || isset($_SERVER['HTTP_SURROGATE_CAPABILITY'])) {
			return true;
		}

		// Some setups provide explicit ESI flags or LiteSpeed-specific ESI headers
		if (isset($_SERVER['HTTP_X_ESI']) || isset($_SERVER['HTTP_X_LSCACHE_ESI']) || defined('LSCWP_ESI') && LSCWP_ESI) {
			return true;
		}

		// Query parameters used by some systems to indicate ESI/fragment requests
		if (isset($_REQUEST['esi']) || isset($_REQUEST['_wp_esi'])) {
			return true;
		}

		// QUIC.cloud or other proxy-level signals sometimes set specific headers
		if (isset($_SERVER['HTTP_X_QUIC_ESI']) || isset($_SERVER['HTTP_X_QUIC_FRAGMENT'])) {
			return true;
		}

		return false;
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
		error_log($message);
	}

	/**
	 * Send minimal headers for SSG responses.
	 *
	 * Includes proper Vary headers to prevent cached SSG content from being
	 * served to the wrong audience (bots vs humans).
	 *
	 * @param int  $postId        Numeric post id for tagging/logging (used in logs only)
	 * @param bool $isBot         Whether the recipient is detected as a bot (used for logging only)
	 * @param int  $contentLength Length of the response content in bytes (optional). When > 0, a `Content-Length` header will be emitted.
	 * @return void
	 */
	public static function sendSSGResponseHeaders(int $postId, bool $isBot = false, int $contentLength = 0): void
	{
		header('X-SSG: true');
		header('X-SSG-Source: static');
		header('X-SSG-Timestamp: ' . time());
		header('X-SSG-Version: 1.0');
		header('X-SSG-Visitor-Type: ' . ($isBot ? 'bot' : 'human'));
		header('Content-Type: text/html; charset=UTF-8');

		if ($contentLength > 0) {
			header('Content-Length: ' . (int) $contentLength);
		}

		// Critical: Add Vary headers to prevent cache confusion between bots and humans
		header('Vary: User-Agent, Accept-Language, Accept-Encoding, X-Requested-With');
		header('Cache-Control: public, max-age=120'); // 2 minutes
		header('X-SSG-Cache-Control: ssg-static');
	}

	/**
	 * Check if SSG cache should be bypassed for current request
	 *
	 * This helps prevent serving stale cached content when configuration
	 * has recently changed or when cache invalidation is needed.
	 *
	 * @return bool True if cache should be bypassed
	 */
	public static function shouldBypassSSGCache(): bool
	{
		// Check for cache bypass parameters
		if (isset($_GET['ssg_no_cache']) || isset($_GET['ssg_refresh'])) {
			return true;
		}

		// Check for recent configuration changes
		$configChangeKey = 'ssg_config_last_changed';
		$lastChange = get_option($configChangeKey, 0);
		$fiveMinutesAgo = time() - 300; // 5 minutes

		if ($lastChange > $fiveMinutesAgo) {
			self::logCoordination('Bypassing SSG cache due to recent configuration change', [
				'last_change' => $lastChange,
				'current_time' => time()
			]);
			return true;
		}

		// Check for maintenance mode
		if (self::shouldSkipDuringMaintenance()) {
			return true;
		}

		return false;
	}

}