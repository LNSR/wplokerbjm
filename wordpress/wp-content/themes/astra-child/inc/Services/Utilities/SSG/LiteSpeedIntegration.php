<?php

namespace AstraChild\Services\Utilities\SSG;

/**
 * LiteSpeed Cache Integration Utilities
 *
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
        // Check for QUIC Cloud headers
        if (isset($_SERVER['HTTP_X_LSCACHE'])) {
            return true;
        }

        // Check for QUIC Cloud domain in server variables
        if (
            isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
            strpos($_SERVER['HTTP_X_FORWARDED_FOR'], 'quic.cloud') !== false
        ) {
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
     */
    public static function shouldSkipDuringMaintenance(): bool
    {
        // Skip during LiteSpeed's purge all operations
        if (isset($_REQUEST['litespeed_purge_all'])) {
            return true;
        }

        // Skip during LiteSpeed's preload operations
        if (defined('LITESPEED_PRELOAD_ACTIVE') && LITESPEED_PRELOAD_ACTIVE) {
            return true;
        }

        // QUIC Cloud specific maintenance
        if (self::isQuicCloudActive() && isset($_SERVER['HTTP_X_QUIC_MAINTENANCE'])) {
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
        $environment = self::isQuicCloudActive() ? 'production-quic' : 'staging';
        $message = "LiteSpeed-SSG Coordination [{$environment}]: {$event}";
        if (!empty($context)) {
            $message .= " - " . json_encode($context);
        }
        error_log($message);
    }
}