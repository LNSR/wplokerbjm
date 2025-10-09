<?php

namespace WPLokerBJM\Services\Utilities\SSG\Integrations;

/**
 * SSG Integration Utilities
 * Handles general SSG coordination and utilities
 */
class SSGIntegration
{
	/**
	 * Get the current environment based on domain patterns
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
				return 'production';
			}
		}

		// Check for environment-specific constants
		if (defined('WP_ENVIRONMENT_TYPE')) {
			$envType = WP_ENVIRONMENT_TYPE;
			if (in_array($envType, ['production', 'staging', 'development', 'local'])) {
				return $envType;
			}
		}

		// Fallback
		return 'staging';
	}

	/**
	 * Get debounce timing recommendations
	 */
	public static function getDebounceTiming(): array
	{
		return [
			'normal_operation' => 30,      // 30 seconds for normal operations
			'coordination' => 60,          // 60 seconds when coordinating with caching layers
			'maintenance_mode' => 120,     // 2 minutes during maintenance
		];
	}

	/**
	 * Log SSG coordination events
	 */
	public static function logCoordination(string $event, array $context = []): void
	{
		$environment = self::getEnvironment();
		$message = "SSG Coordination [{$environment}]: {$event}";
		if (!empty($context)) {
			$message .= " - " . json_encode($context);
		}
		error_log($message);
	}
}