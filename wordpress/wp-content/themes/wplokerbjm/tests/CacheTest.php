<?php

namespace WPLokerBJM\Tests;

use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Shared\Cache\CacheKey;
use WPLokerBJM\Configs\Credential\CredentialConfig;

class CacheTest extends WplokerbjmTestCase
{
    private RedisAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        // Define LSOC_PREFIX for testing (mimics LiteSpeed's behavior)
        if (!defined('LSOC_PREFIX')) {
            define('LSOC_PREFIX', 'cf8e0');
        }

        // Create an instance of RedisAdapter for testing
        $credentials = CredentialConfig::RedisCredential();
        $this->adapter = new RedisAdapter($credentials);
    }

    private function getRedisConnection(): \Redis|false {
        $bind = \Closure::bind(static function(RedisAdapter $redis){
            return $redis->resolveConnection();
        }, null, RedisAdapter::class);

        return $bind($this->adapter);
    }

    public function testGetConnection()
    {
        if (!extension_loaded('redis')) {
            $this->fail('Redis extension is not loaded. Install with: pecl install redis && echo "extension=redis.so" >> php.ini');
        }

        echo "\n\033[1;35m🔴 Redis Connection Test\033[0m\n";

        $credentials = CredentialConfig::RedisCredential();

        // Debug: Show what constants are defined
        echo "\033[0;36mConfiguration:\033[0m\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_SOCK: " . ($credentials['sock'] ? "\033[0;32m" . $credentials['sock'] . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_HOST: " . ($credentials['host'] ? "\033[0;32m" . $credentials['host'] . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_PORT: " . ($credentials['port'] ? "\033[0;32m" . $credentials['port'] . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_PASSWORD: " . ($credentials['password'] ? "\033[0;32m" . '{REDACTED}' . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_DATABASE: " . ($credentials['database'] !== null ? "\033[0;32m" . $credentials['database'] . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";

        $redis = $this->getRedisConnection();

        if ($redis === false) {
            echo "\033[0;31m❌ Redis connection failed\033[0m\n";
            $this->fail('Redis connection failed - check Redis server and configuration. Is Redis running on ' . ($credentials['host'] ?: 'localhost') . ':' . ($credentials['port'] ?: 6379) . '?');
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

        $credentials = CredentialConfig::RedisCredential();

        // Debug: Show TCP configuration
        echo "\033[0;36mTCP Configuration:\033[0m\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_HOST: " . ($credentials['host'] ? "\033[0;32m" . $credentials['host'] . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_PORT: " . ($credentials['port'] ? "\033[0;32m" . $credentials['port'] . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_PASSWORD: " . ($credentials['password'] ? "\033[0;32m" . '{REDACTED}' . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";
        echo "  \033[0;33m•\033[0m WP_REDIS_DATABASE: " . ($credentials['database'] !== null ? "\033[0;32m" . $credentials['database'] . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n";

        $host = $credentials['host'] ?: 'localhost';
        $port = $credentials['port'] ?: 6379;
        // In Docker container environment, use 'redis' service name to reach Redis container
        if ((getenv('WP_ENV') === 'development' || getenv('WP_ENV') === 'production') && file_exists('/.dockerenv')) {
            $host = 'redis';
        }
        $password = $credentials['password'];
        $database = $credentials['database'] ?: 0;

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

    public function testDeletePattern()
    {
        // Test pattern-based deletion using Redis::deletePattern
        if (!extension_loaded('redis')) {
            $this->fail('Redis extension is not loaded. Install with: pecl install redis && echo "extension=redis.so" >> php.ini');
        }

        echo "\n\033[1;35m🔴 Cache Delete Pattern Test\033[0m\n";

        // Get Redis connection
        $redis = $this->getRedisConnection();
        if ($redis === false) {
            echo "\033[0;31m❌ Redis connection failed\033[0m\n";
            $this->fail('Redis connection failed - cannot test deletePattern');
        }

        // Determine the salt (LSOC_PREFIX or fallback)
        $wp_content_dir = '/var/www/html/wp-content'; // Hardcoded for test environment
        $lscwp_dir = (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : $wp_content_dir . '/plugins') . '/litespeed-cache/';
        $cls_file = $lscwp_dir . 'src/object-cache-wp.cls.php';
        $expectedSalt = defined('LSOC_PREFIX') && is_string(LSOC_PREFIX) && !empty(LSOC_PREFIX) ? LSOC_PREFIX : substr(md5($cls_file), -5);

        echo "\033[0;36mPattern Configuration:\033[0m\n";
        echo "  \033[0;33m•\033[0m Expected Salt: \033[0;32m{$expectedSalt}\033[0m\n";
        echo "  \033[0;33m•\033[0m Cache Prefix: \033[0;32m" . CacheKey::OBJECT_CACHE_PREFIX . "\033[0m\n";

        // Create test keys directly in Redis with the correct format
        $testPattern = 'phpunit_test_pattern_' . time() . '_*';
        $testKeys = [
            $expectedSalt . CacheKey::OBJECT_CACHE_PREFIX . '.phpunit_test_pattern_' . time() . '_1',
            $expectedSalt . CacheKey::OBJECT_CACHE_PREFIX . '.phpunit_test_pattern_' . time() . '_2',
            $expectedSalt . CacheKey::OBJECT_CACHE_PREFIX . '.phpunit_test_pattern_' . time() . '_3',
        ];

        echo "\n\033[0;36mSetting up test keys:\033[0m\n";
        foreach ($testKeys as $key) {
            $redis->set($key, 'test_value', 300); // 5 minute expiry
            echo "  \033[0;32m✓\033[0m Set key: {$key}\n";
        }

        // Verify keys exist
        $existingKeys = $redis->keys($expectedSalt . CacheKey::OBJECT_CACHE_PREFIX . '.' . $testPattern);
        echo "\n\033[0;36mKeys before deletion:\033[0m\n";
        echo "  \033[0;33m•\033[0m Found " . count($existingKeys) . " keys matching pattern\n";
        $this->assertCount(3, $existingKeys, 'Test keys were not set correctly');

        // Call deletePattern
        $patternToDelete = 'phpunit_test_pattern_' . time() . '_*';
        $deletedCount = $this->adapter->deletePattern([$patternToDelete]);

        echo "\n\033[0;36mDelete Pattern Result:\033[0m\n";
        echo "  \033[0;33m•\033[0m Deleted count: \033[0;32m{$deletedCount}\033[0m\n";
        $this->assertEquals(3, $deletedCount, 'deletePattern did not delete the expected number of keys');

        // Verify keys are gone
        $remainingKeys = $redis->keys($expectedSalt . CacheKey::OBJECT_CACHE_PREFIX . '.' . $testPattern);
        echo "  \033[0;33m•\033[0m Remaining keys: \033[0;32m" . count($remainingKeys) . "\033[0m\n";
        $this->assertCount(0, $remainingKeys, 'Keys were not deleted by deletePattern');

        echo "\n\033[0;32m✅ Delete Pattern Test Passed\033[0m\n";
    }
}