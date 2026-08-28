<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support;

use \DI\Container;
use WPLokerBJM\Core\Container\WPLokerBJMContainer;
use Dotenv\Dotenv;

/**
 * ProxyContainer
 *
 * Single entry-point for PHPUnit tests.
 * - Loads env (best-effort)
 * - Defines WordPress constants used by the theme
 * - Provides minimal WordPress function/class stubs
 * - Provides an in-memory WP object cache implementation
 * - Exposes the real theme DI container via WPLokerBJM\Core\Container
 */
final class ProxyContainer
{
    private static bool $booted = false;
    private static ?Container $container = null;

    /**
     * Boot test runtime once per PHPUnit process.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        self::loadEnvFiles();
        self::defineCoreConstants();

        // Build container lazily to avoid loading lots of classes before stubs exist.
    }

    /**
     * Reset per-test mutable state (cache, HTTP mocks, etc).
     */
    public static function resetPerTest(): void
    {
        // Reset in-memory object cache
        $GLOBALS['__wplokerbjm_wp_object_cache'] = [];

        // Reset tracked WordPress hook registrations
        $GLOBALS['__wplokerbjm_registered_hooks'] = [];
    }

    public static function container(): Container
    {
        if (self::$container instanceof Container) {
            return self::$container;
        }

        // Ensure runtime is booted before container initialization.
        self::boot();

        self::$container = WPLokerBJMContainer::getContainer();
        return self::$container;
    }

    private static function themeRoot(): string
    {
        // tests/Support -> tests -> theme root
        return dirname(__DIR__, 2);
    }

    private static function loadEnvFiles(): void
    {
        // Load theme .env
        if (file_exists(self::themeRoot() . '/.env')) {
            $dotenv = Dotenv::createImmutable(self::themeRoot());
            $dotenv->load();
        }
    }

    private static function defineCoreConstants(): void
    {
        // Ensure the theme behaves as in development for tests.
        if (!defined('WP_ENV')) {
            define('WP_ENV', getenv('WP_ENV') ?: 'development');
        }

        // Some code branches on WP_ENVIRONMENT_TYPE.
        if (!defined('WP_ENVIRONMENT_TYPE')) {
            define('WP_ENVIRONMENT_TYPE', (WP_ENV === 'development') ? 'development' : 'production');
        }

        // lowongan ingest
        if (!defined('WPLBJM_API_BASE_URL_DEV')) {
            define('WPLBJM_API_BASE_URL_DEV', getenv('WPLBJM_API_BASE_URL_DEV') ?: 'https://localhost');
        }
        if (!defined('WPLBJM_JWT_DEV')) {
            define('WPLBJM_JWT_DEV', getenv('WPLBJM_JWT_DEV') ?: null);
        }

        // Redis constants like WordPress does
        if (!defined('WP_REDIS_SOCK')) {
            define('WP_REDIS_SOCK', getenv('REDIS_SOCK') ?: null);
        }
        if (!defined('WP_REDIS_HOST')) {
            define('WP_REDIS_HOST', getenv('REDIS_HOST') ?: 'localhost');
        }
        if (!defined('WP_REDIS_PORT')) {
            define('WP_REDIS_PORT', getenv('REDIS_PORT') ?: 6379);
        }
        if (!defined('WP_REDIS_PASSWORD')) {
            define('WP_REDIS_PASSWORD', getenv('REDIS_PWD') ?: null);
        }
        if (!defined('WP_REDIS_DATABASE')) {
            define('WP_REDIS_DATABASE', getenv('REDIS_DB') ?: 0);
        }
    }
}
