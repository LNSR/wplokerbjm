<?php

namespace WPLokerBJM\Shared\Log;

use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * Generic Logger for application events
 *
 * Provides structured logging with environment context and different log levels.
 * Log entries are buffered in-memory and flushed on WordPress shutdown via
 * LoggerFlushHooks (see GlobalHooks.php). If the batch write fails, each
 * entry is written individually as a graceful fallback.
 *
 * @phpstan-type LogLevel 'DEBUG'|'INFO'|'WARNING'|'ERROR'
 * @phpstan-type LogEntry array{timestamp: string, environment: string, level: string, category: string, message: string, context: array}
 */
class Logger
{

    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';

    /** @var LogEntry[] In-memory buffer of pending log entries */
    private static array $buffer = [];

    /** Guard flag to prevent re-entry during flush() */
    private static bool $flushing = false;

    /**
     * Get the current environment based on WordPress configuration
     * @return 'development'|'local'|'production'
     */
    private static function getEnvironment(): string
    {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE;
        }

        if (SharedUtils::isDevelopment()) {
            return 'development';
        }

        // Fallback detection for edge cases
        // if (SharedUtils::isLocalhost()) {
        //     return 'local';
        // }

        return 'production'; // Default fallback
    }

    /**
     * Log a message with context
     *
     * Buffers the entry in-memory instead of writing immediately.
     * The buffer is flushed on WordPress shutdown via a dedicated
     * shutdown handler with a graceful fallback on failure.
     *
     * @phpstan-param LogLevel $level
     * @param string $category Log category (e.g., 'Cache', 'API', 'Core')
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function log(string $level, string $category, string $message, array $context = []): void
    {
        self::$buffer[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => self::getEnvironment(),
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * Flush all buffered log entries to error_log.
     *
     * Attempts a single batch write; if it fails, falls back to writing
     * each entry individually. The buffer is cleared regardless of outcome.
     * Safe against re-entry (nested calls during flush are no-ops).
     * @see \WPLokerBJM\Core\LoggerHooks::flushBuffer() use this to setup logger
     * ! Strictly used only during Wordpress shutdown hook
     */
    public static function flush(): void
    {
        if (self::$flushing) {
            return;
        }
        self::$flushing = true;

        $entries = self::$buffer;
        self::$buffer = [];

        if ($entries === []) {
            self::$flushing = false;
            return;
        }

        // Format all entries
        $formatted = array_map(self::formatEntry(...), $entries);
        $batch = implode("\n", $formatted);

        // Try batch write; fall back to individual writes on failure
        if (error_log($batch) === false) {
            foreach ($formatted as $entry) {
                error_log($entry);
            }
        }

        self::$flushing = false;
    }

    /**
     * Log debug message
     *
     * @param string $category Log category (e.g., 'Cache', 'API', 'Core')
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function debug(string $category, string $message, array $context = []): void
    {
        self::log(self::LEVEL_DEBUG, $category, $message, $context);
    }

    /**
     * Log info message
     *
     * @param string $category Log category (e.g., 'Cache', 'API', 'Core')
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function info(string $category, string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $category, $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $category Log category (e.g., 'Cache', 'API', 'Core')
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function warning(string $category, string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $category, $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $category Log category (e.g., 'Cache', 'API', 'Core')
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function error(string $category, string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $category, $message, $context);
    }

    /**
     * Format a single log entry into its string representation.
     *
     * @phpstan-param LogEntry $entry
     * @return string Formatted log line with ANSI colour codes and emoji
     */
    private static function formatEntry(array $entry): string
    {
        $colorCode = self::getLevelColor($entry['level']);
        $levelEmoji = self::getLevelEmoji($entry['level']);

        $message = "\033{$colorCode}[{$entry['timestamp']}] [{$entry['environment']}] [{$levelEmoji} {$entry['level']}] [{$entry['category']}]\033[0m: {$entry['message']}";

        if ($entry['context'] !== []) {
            $message .= ' - ' . json_encode($entry['context']);
        }

        return $message;
    }

    /**
     * Get ANSI color code for log level
     *
     * @phpstan-param LogLevel $level
     * @return string ANSI color code for log level
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
     *
     * @phpstan-param LogLevel $level
     * @return string Emoji for log level
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
