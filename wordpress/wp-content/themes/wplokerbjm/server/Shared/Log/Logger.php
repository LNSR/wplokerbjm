<?php

namespace WPLokerBJM\Shared\Log;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * Monolog-backed logger preserving the original static API.
 *
 * Log entries are buffered in-memory by Monolog's BufferHandler and flushed
 * on WordPress shutdown via Init::shutdown()/SharedUtils, or explicitly via
 * flush() from error paths and destructors. Categories are mapped to Monolog
 * channels, while the environment is attached to each record's extra data.
 *
 * If Monolog is unavailable (e.g. during a deployment before Composer has
 * installed it), logging degrades to a plain error_log() fallback instead of
 * breaking the request.
 *
 * @phpstan-type LogLevel 'DEBUG'|'INFO'|'WARNING'|'ERROR'|key-of<Logger::LEVEL_*>
 */
class Logger
{

    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';

    /** Maximum number of buffered records before an automatic flush */
    private const BUFFER_LIMIT = 1000;

    private static ?MonologLogger $logger = null;

    private static ?BufferHandler $buffer = null;

    /** @var array<string, MonologLogger> Category-keyed child loggers */
    private static array $channels = [];

    /** Guard flag to prevent re-entry during flush() */
    private static bool $flushing = false;

    /** Prevents repeated Monolog boot attempts after a failure */
    private static bool $bootFailed = false;

    /**
     * Eagerly initialise Monolog.
     *
     * Called from the mu-plugin bootstrap so the theme's Monolog classes are
     * loaded before regular plugins can prepend their own autoloaders (for
     * example fast-indexing-api's bundled Monolog 2.x/psr-log 1.x).
     */
    public static function preload(): void
    {
        self::boot();
    }

    /**
     * Boot the Monolog logger and its buffered error_log handler.
     *
     * @return bool Whether Monolog is ready to accept records.
     */
    private static function boot(): bool
    {
        if (self::$logger !== null) {
            return true;
        }

        if (self::$bootFailed) {
            return false;
        }

        // Monolog 3 cannot implement the psr extension's untyped PSR-3 v1
        // interface; PHP fatals during autoload before this try/catch can run.
        if (self::hasIncompatiblePsrLogger()) {
            self::$bootFailed = true;
            \error_log('Monolog disabled: the loaded Psr\\Log\\LoggerInterface is untyped (PSR-3 v1/v2). Remove the psr PHP extension or downgrade Monolog.');

            return false;
        }

        try {
            $handler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Level::Debug);
            $handler->setFormatter(new LineFormatter(
                '[%datetime%] %channel%.%level_name%: %message% %context% %extra%'
            ));

            self::$buffer = new BufferHandler(
                new WhatFailureGroupHandler([$handler]),
                self::BUFFER_LIMIT,
                Level::Debug,
                true,
                true,
            );

            self::$logger = new MonologLogger('wplokerbjm', [self::$buffer]);
            self::$logger->pushProcessor(static function (LogRecord $record): LogRecord {
                $record->extra['environment'] = self::getEnvironment();

                return $record;
            });

            // Bundled libraries (e.g. fast-indexing-api's Google API client) use
            // Monolog 2.x handlers. Load the theme's monolog 3 versions now so a
            // later-prepended plugin autoloader cannot supply incompatible ones.
            \class_exists(StreamHandler::class);
            \class_exists(SyslogHandler::class);

            return true;
        } catch (\Throwable $e) {
            self::$bootFailed = true;
            \error_log('Monolog initialization failed; using error_log() fallback: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Detect an already-loaded PSR-3 v1/v2 interface.
     *
     * The psr PHP extension aliases its untyped interface to
     * Psr\Log\LoggerInterface. Monolog 3 requires the typed PSR-3 3.x
     * contract, and an incompatible declaration fatals during autoload.
     * Checking before Monolog loads lets us fall back to error_log().
     */
    private static function hasIncompatiblePsrLogger(): bool
    {
        if (!\interface_exists('Psr\\Log\\LoggerInterface', false)) {
            return false;
        }

        try {
            $parameter = (new \ReflectionMethod('Psr\\Log\\LoggerInterface', 'emergency'))
                ->getParameters()[0] ?? null;
        } catch (\ReflectionException) {
            return true;
        }

        return $parameter === null || !$parameter->hasType();
    }

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
     * Buffers the entry in Monolog's in-memory BufferHandler instead of
     * writing immediately. The buffer is flushed on WordPress shutdown via a
     * dedicated shutdown handler, or when BUFFER_LIMIT is reached.
     *
     * @phpstan-param LogLevel $level
     * @param string $category Log category (e.g., 'Cache', 'API', 'Core')
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function log(string $level, string $category, string $message, array $context = []): void
    {
        if (!self::boot()) {
            self::fallback($level, $category, $message, $context);

            return;
        }

        try {
            self::channel($category)->log($level, $message, $context);
        } catch (\Throwable) {
            // Logging must never take down the caller.
            self::fallback($level, $category, $message, $context);
        }
    }

    /**
     * Resolve (and cache) a Monolog child logger for a category.
     */
    private static function channel(string $category): MonologLogger
    {
        $logger = self::$logger;
        if ($logger === null) {
            throw new \RuntimeException('Monolog logger is not initialized.');
        }

        return self::$channels[$category] ??= $logger->withName($category);
    }

    /**
     * Write a log line without Monolog.
     *
     * Used when Monolog cannot be loaded or a handler throws unexpectedly, so
     * that an unavailable logging backend never breaks the request.
     *
     * @param array $context Additional context data
     */
    private static function fallback(string $level, string $category, string $message, array $context = []): void
    {
        $line = \sprintf(
            '[%s] [%s] [%s] [%s]: %s',
            new \DateTimeImmutable()->format('Y-m-d H:i:s.u'),
            self::getEnvironment(),
            $level,
            $category,
            $message,
        );

        if ($context !== []) {
            $encoded = \json_encode($context);
            $line .= ' - ' . ($encoded !== false ? $encoded : '[unserializable context]');
        }

        \error_log($line);
    }

    /**
     * Flush all buffered log entries to error_log.
     *
     * Delegates to Monolog's BufferHandler::flush(), which writes the batch to
     * the wrapped handler and clears the buffer. Safe against re-entry (nested
     * calls during flush are no-ops).
     * @see \WPLokerBJM\Core\Container\Init also serve as flusher
     * ! Strictly used only during __destruct sequence
     */
    public static function flush(): void
    {
        if (self::$flushing || self::$buffer === null) {
            return;
        }

        self::$flushing = true;

        try {
            self::$buffer->flush();
        } catch (\Throwable $e) {
            \error_log('Monolog flush failed: ' . $e->getMessage());
        } finally {
            self::$flushing = false;
        }
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
}
