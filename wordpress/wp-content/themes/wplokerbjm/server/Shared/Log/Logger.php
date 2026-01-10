<?php

namespace WPLokerBJM\Shared\Log;

use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * Generic Logger for application events
 *
 * Provides structured logging with environment context and different log levels.
 */
class Logger
{
    /**
     * Log levels
     */
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';

    /**
     * Get the current environment based on WordPress configuration
     */
    private static function getEnvironment(): string
    {
        // Use WP_ENVIRONMENT_TYPE if already set by wp-config-extra.php
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE;
        }

        // Fallback detection for edge cases
        if (SharedUtils::isLocalhost()) {
            return 'local';
        }

        return 'production'; // Default fallback
    }

    /**
     * Log a message with context
     *
     * @param string $level Log level (DEBUG, INFO, WARNING, ERROR)
     * @param string $category Log category (e.g., 'Cache', 'API', 'Core')
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function log(string $level, string $category, string $message, array $context = []): void
    {
        $environment = self::getEnvironment();
        $timestamp = date('Y-m-d H:i:s');

        // Add color coding based on log level
        $colorCode = self::getLevelColor($level);
        $levelEmoji = self::getLevelEmoji($level);

        $logMessage = "\033{$colorCode}[{$timestamp}] [{$environment}] [{$levelEmoji} {$level}] [{$category}]\033[0m: {$message}";

        if (!empty($context)) {
            $logMessage .= " - " . json_encode($context);
        }

        error_log($logMessage);
    }

    /**
     * Log debug message
     */
    public static function debug(string $category, string $message, array $context = []): void
    {
        self::log(self::LEVEL_DEBUG, $category, $message, $context);
    }

    /**
     * Log info message
     */
    public static function info(string $category, string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $category, $message, $context);
    }

    /**
     * Log warning message
     */
    public static function warning(string $category, string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $category, $message, $context);
    }

    /**
     * Log error message
     */
    public static function error(string $category, string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $category, $message, $context);
    }

    /**
     * Get ANSI color code for log level
     */
    private static function getLevelColor(string $level): string
    {
        return match ($level) {
            self::LEVEL_DEBUG => '[1;90m',    // Bright black (gray)
            self::LEVEL_INFO => '[1;36m',     // Bright cyan
            self::LEVEL_WARNING => '[1;33m',  // Bright yellow
            self::LEVEL_ERROR => '[1;31m',    // Bright red
            default => '[0m',                 // Reset/default
        };
    }

    /**
     * Get emoji for log level
     */
    private static function getLevelEmoji(string $level): string
    {
        return match ($level) {
            self::LEVEL_DEBUG => '🐛',
            self::LEVEL_INFO => 'ℹ️',
            self::LEVEL_WARNING => '⚠️',
            self::LEVEL_ERROR => '❌',
            default => '📝',
        };
    }
}
