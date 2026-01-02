<?php

namespace WPLokerBJM\Tests;

use WPLokerBJM\Tests\Support\ProxyContainer;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Shared\Cache\Cache;

class CacheTest extends WplokerbjmTestCase
{
    
    public function testGetRedisConnectionWithEnvironmentConfig()
    {
        // Test Redis connection using environment variables (like WordPress)
        if (!extension_loaded('redis')) {
            $this->fail('Redis extension is not loaded. Install with: pecl install redis && echo "extension=redis.so" >> php.ini');
        }

        echo "\n\033[1;35m🔴 Redis Connection Test\033[0m\n";

        // Debug: Show what constants are defined
        echo "\033[0;36mConfiguration:\033[0m\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_SOCK: " . (defined('WP_REDIS_SOCK') ? "\033[0;32m" . WP_REDIS_SOCK . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_HOST: " . (defined('WP_REDIS_HOST') ? "\033[0;32m" . WP_REDIS_HOST . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_PORT: " . (defined('WP_REDIS_PORT') ? "\033[0;32m" . WP_REDIS_PORT . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_PASSWORD: " . (defined('WP_REDIS_PASSWORD') ? "\033[0;32m" . (WP_REDIS_PASSWORD ? '{REDACTED}' : 'empty') . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_DATABASE: " . (defined('WP_REDIS_DATABASE') ? "\033[0;32m" . WP_REDIS_DATABASE . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";

        $redis = Cache::getRedisConnection();

        if ($redis === false) {
            echo "\033[0;31m❌ Redis connection failed\033[0m\n";
            $this->fail('Redis connection failed - check Redis server and configuration. Is Redis running on ' . WP_REDIS_HOST . ':' . WP_REDIS_PORT . '?');
        }

        echo "\033[0;32m✅ Redis connection successful\033[0m\n";
        $this->assertInstanceOf(\Redis::class, $redis);

        // Test that we can actually perform Redis operations
        $testKey = 'phpunit_test_key_' . time();
        $testValue = 'test_value_' . rand();

        echo "\n\033[0;36mTesting Redis operations:\033[0m\n";

        // Set a value
        $setResult = $redis->set($testKey, $testValue, 60); // 60 second expiry
        if ($setResult !== false) {
            echo "  \033[0;32m✓\033[0m Set operation successful\n";
        } else {
            echo "  \033[0;31m✗\033[0m Set operation failed\n";
        }
        $this->assertTrue($setResult !== false, 'Failed to set Redis value');

        // Get the value back
        $getResult = $redis->get($testKey);
        if ($getResult === $testValue) {
            echo "  \033[0;32m✓\033[0m Get operation successful\n";
        } else {
            echo "  \033[0;31m✗\033[0m Get operation failed\n";
        }
        $this->assertEquals($testValue, $getResult, 'Failed to get Redis value');

        // Clean up
        $redis->del($testKey);
        echo "  \033[0;32m✓\033[0m Cleanup completed\n";
        echo "\n";
    }

    public function testTCPConnection()
    {
        // Test direct TCP connection to Redis (bypassing socket)
        if (!extension_loaded('redis')) {
            $this->fail('Redis extension is not loaded. Install with: pecl install redis && echo "extension=redis.so" >> php.ini');
        }

        echo "\n\033[1;35m🔴 Redis TCP Connection Test\033[0m\n";

        // Debug: Show TCP configuration
        echo "\033[0;36mTCP Configuration:\033[0m\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_HOST: " . (defined('WP_REDIS_HOST') ? "\033[0;32m" . WP_REDIS_HOST . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_PORT: " . (defined('WP_REDIS_PORT') ? "\033[0;32m" . WP_REDIS_PORT . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_PASSWORD: " . (defined('WP_REDIS_PASSWORD') ? "\033[0;32m" . (WP_REDIS_PASSWORD ? '{REDACTED}' : 'empty') . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_DATABASE: " . (defined('WP_REDIS_DATABASE') ? "\033[0;32m" . WP_REDIS_DATABASE . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";

        $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : 'localhost';
        $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;
        // In Docker container environment, use 'redis' service name to reach Redis container
        if ((getenv('WP_ENV') === 'development' || getenv('WP_ENV') === 'production') && file_exists('/.dockerenv')) {
            $host = 'redis';
        }
        $password = defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : null;
        $database = defined('WP_REDIS_DATABASE') ? WP_REDIS_DATABASE : 0;

        $redis = new \Redis();

        $connected = $redis->connect($host, $port);
        if (!$connected) {
            echo "\033[0;31m❌ TCP Redis connection failed\033[0m\n";
            $this->fail("TCP Redis connection failed - check Redis server at {$host}:{$port}");
        }

        echo "\033[0;32m✅ TCP Redis connection successful\033[0m\n";

        // Authenticate if password is set
        if ($password) {
            if (!$redis->auth($password)) {
                echo "\033[0;31m❌ TCP Redis authentication failed\033[0m\n";
                $this->fail('TCP Redis authentication failed');
            }
            echo "\033[0;32m✅ TCP Redis authentication successful\033[0m\n";
        }

        // Select database
        if (!$redis->select($database)) {
            echo "\033[0;31m❌ TCP Redis database selection failed\033[0m\n";
            $this->fail('TCP Redis database selection failed');
        }
        echo "\033[0;32m✅ TCP Redis database selection successful\033[0m\n";

        // Test that we can actually perform Redis operations
        $testKey = 'phpunit_tcp_test_key_' . time();
        $testValue = 'tcp_test_value_' . rand();

        echo "\n\033[0;36mTesting TCP Redis operations:\033[0m\n";

        // Set a value
        $setResult = $redis->set($testKey, $testValue, 60); // 60 second expiry
        if ($setResult !== false) {
            echo "  \033[0;32m✓\033[0m TCP Set operation successful\n";
        } else {
            echo "  \033[0;31m✗\033[0m TCP Set operation failed\n";
        }
        $this->assertTrue($setResult !== false, 'Failed to set Redis value via TCP');

        // Get the value back
        $getResult = $redis->get($testKey);
        if ($getResult === $testValue) {
            echo "  \033[0;32m✓\033[0m TCP Get operation successful\n";
        } else {
            echo "  \033[0;31m✗\033[0m TCP Get operation failed\n";
        }
        $this->assertEquals($testValue, $getResult, 'Failed to get Redis value via TCP');

        // Clean up
        $redis->del($testKey);
        echo "  \033[0;32m✓\033[0m TCP Cleanup completed\n";
        echo "\n";
    }
}