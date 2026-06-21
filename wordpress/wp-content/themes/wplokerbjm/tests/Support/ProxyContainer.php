<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support;

use Psr\Container\ContainerInterface;
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
    private static ?ContainerInterface $container = null;

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

    public static function container(): ContainerInterface
    {
        if (self::$container instanceof ContainerInterface) {
            return self::$container;
        }

        // Ensure runtime is booted before container initialization.
        self::boot();

        self::$container = WPLokerBJMContainer::getContainer();
        return self::$container;
    }

    /**
     * Invoke a private/protected method on an object using reflection.
     *
     * @param object $object The object instance
     * @param string $methodName The method name to invoke
     * @param array $args Arguments to pass to the method
     * @return mixed The method's return value
     * @throws \ReflectionException If method doesn't exist or can't be accessed
     */
    public static function invokePrivateMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionObject($object);
        $method = $reflection->getMethod($methodName);
        return $method->invoke($object, ...$args);
    }

    /**
     * Get a private/protected property value from an object using reflection.
     *
     * @param object $object The object instance
     * @param string $propertyName The property name to access
     * @return mixed The property value
     * @throws \ReflectionException If property doesn't exist or can't be accessed
     */
    public static function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionObject($object);
        $property = $reflection->getProperty($propertyName);
        return $property->getValue($object);
    }

    /**
     * Set a private/protected property value on an object using reflection.
     *
     * @param object $object The object instance
     * @param string $propertyName The property name to set
     * @param mixed $value The value to set
     * @throws \ReflectionException If property doesn't exist or can't be accessed
     */
    public static function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionObject($object);
        $property = $reflection->getProperty($propertyName);
        $property->setValue($object, $value);
    }

    /**
     * Get a private/protected static method using reflection.
     *
     * @param string $className The class name
     * @param string $methodName The method name to access
     * @return mixed The method's return value
     * @throws \ReflectionException If method doesn't exist or can't be accessed
     */
    public static function getPrivateStaticMethod(string $className, string $methodName): mixed
    {
        $reflection = new \ReflectionClass($className);
        $method = $reflection->getMethod($methodName);
        return $method->invoke(null);
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
