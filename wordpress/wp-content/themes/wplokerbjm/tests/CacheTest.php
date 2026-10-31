<?php

namespace WPLokerBJM\Tests;

use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Tests\Support\Terminal;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Shared\Cache\CacheKey;
use WPLokerBJM\Configs\Credential\CredentialConfig;

class CacheTest extends WplokerbjmTestCase
{
    private RedisAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        // Simulate LSCWP v7.x LSOC_PREFIX (sha256 site identity), normally
        // defined by WP_Object_Cache::__construct() in
        // litespeed-cache/src/object-cache-wp.cls.php.
        if (!defined('LSOC_PREFIX')) {
            define('LSOC_PREFIX', hash('sha256', 'wplokerbjm-test-identity'));
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

        Terminal::out("\n\033[1;35m🔴 Redis Connection Test\033[0m\n");

        $credentials = CredentialConfig::RedisCredential();

        // Debug: Show what constants are defined
        Terminal::out("\033[0;36mConfiguration:\033[0m\n");
        Terminal::out("  \033[0;33m•\033[0m WP_REDIS_SOCK: " . ($credentials->sock ? "\033[0;32m" . $credentials->sock . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n");
        Terminal::out("  \033[0;33m•\033[0m WP_REDIS_HOST: " . ($credentials->host ? "\033[0;32m" . $credentials->host . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n");
        Terminal::out("  \033[0;33m•\033[0m WP_REDIS_PORT: " . ($credentials->port ? "\033[0;32m" . $credentials->port . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n");
        Terminal::out("  \033[0;33m•\033[0m WP_REDIS_PASSWORD: " . ($credentials->password ? "\033[0;32m" . '{REDACTED}' . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n");
        Terminal::out("  \033[0;33m•\033[0m WP_REDIS_DATABASE: " . ($credentials->database !== null ? "\033[0;32m" . $credentials->database . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n");

        $redis = $this->getRedisConnection();

        if ($redis === false) {
            Terminal::out("\033[0;31m❌ Redis connection failed\033[0m\n");
            $this->fail('Redis connection failed - check Redis server and configuration. Is Redis running on ' . ($credentials->host ?: 'localhost') . ':' . ($credentials->port ?: 6379) . '?');
        }

        Terminal::out("\033[0;32m✅ Redis connection successful\033[0m\n");
        $this->assertInstanceOf(\Redis::class, $redis);

        // Test that we can actually perform Redis operations
        $testKey = 'phpunit_test_key_' . time();
        $testValue = 'test_value_' . rand();

        Terminal::out("\n\033[0;36mTesting Redis operations:\033[0m\n");

        // Set a value
        $setResult = $redis->set($testKey, $testValue, 60); // 60 second expiry
        if ($setResult !== false) {
            Terminal::out("  \033[0;32m✓\033[0m Set operation successful\n");
        } else {
            Terminal::out("  \033[0;31m✗\033[0m Set operation failed\n");
        }
        $this->assertTrue($setResult !== false, 'Failed to set Redis value');

        // Get the value back
        $getResult = $redis->get($testKey);
        if ($getResult === $testValue) {
            Terminal::out("  \033[0;32m✓\033[0m Get operation successful\n");
        } else {
            Terminal::out("  \033[0;31m✗\033[0m Get operation failed\n");
        }
        $this->assertEquals($testValue, $getResult, 'Failed to get Redis value');

        // Clean up
        $redis->del($testKey);
        Terminal::out("  \033[0;32m✓\033[0m Cleanup completed\n");
        Terminal::out("\n");
    }

    public function testTCPConnection()
    {
        // Test direct TCP connection to Redis (bypassing socket)
        if (!extension_loaded('redis')) {
            $this->fail('Redis extension is not loaded. Install with: pecl install redis && echo "extension=redis.so" >> php.ini');
        }

        Terminal::out("\n\033[1;35m🔴 Redis TCP Connection Test\033[0m\n");

        $credentials = CredentialConfig::RedisCredential();

        // Debug: Show TCP configuration
        Terminal::out("\033[0;36mTCP Configuration:\033[0m\n");
        Terminal::out("  \033[0;33m•\033[0m WP_REDIS_HOST: " . ($credentials->host ? "\033[0;32m" . $credentials->host . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n");
        Terminal::out("  \033[0;33m•\033[0m WP_REDIS_PORT: " . ($credentials->port ? "\033[0;32m" . $credentials->port . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n");
        Terminal::out("  \033[0;33m•\033[0m WP_REDIS_PASSWORD: " . ($credentials->password ? "\033[0;32m" . '{REDACTED}' . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n");
        Terminal::out("  \033[0;33m•\033[0m WP_REDIS_DATABASE: " . ($credentials->database !== null ? "\033[0;32m" . $credentials->database . "\033[0m" : "\033[0;31mnot defined\033[0m") . "\n");

        $host = $credentials->host ?: 'localhost';
        $port = $credentials->port ?: 6379;
        // In Docker container environment, use 'redis' service name to reach Redis container
        if ((getenv('WP_ENV') === 'development' || getenv('WP_ENV') === 'production') && file_exists('/.dockerenv')) {
            $host = 'redis';
        }

        $redis = new \Redis();

        $connected = $redis->connect($host, $port);
        if (!$connected) {
            Terminal::out("\033[0;31m❌ TCP Redis connection failed\033[0m\n");
            $this->fail("TCP Redis connection failed - check Redis server at {$host}:{$port}");
        }

        Terminal::out("\033[0;32m✅ TCP Redis connection successful\033[0m\n");

        // Authenticate if password is set
        if ($credentials->password) {
            if (!$redis->auth($credentials->password)) {
                Terminal::out("\033[0;31m❌ TCP Redis authentication failed\033[0m\n");
                $this->fail('TCP Redis authentication failed');
            }
            Terminal::out("\033[0;32m✅ TCP Redis authentication successful\033[0m\n");
        }

        // Select database
        if (!$redis->select($credentials->database)) {
            Terminal::out("\033[0;31m❌ TCP Redis database selection failed\033[0m\n");
            $this->fail('TCP Redis database selection failed');
        }
        Terminal::out("\033[0;32m✅ TCP Redis database selection successful\033[0m\n");

        // Test that we can actually perform Redis operations
        $testKey = 'phpunit_tcp_test_key_' . time();
        $testValue = 'tcp_test_value_' . rand();

        Terminal::out("\n\033[0;36mTesting TCP Redis operations:\033[0m\n");

        // Set a value
        $setResult = $redis->set($testKey, $testValue, 60); // 60 second expiry
        if ($setResult !== false) {
            Terminal::out("  \033[0;32m✓\033[0m TCP Set operation successful\n");
        } else {
            Terminal::out("  \033[0;31m✗\033[0m TCP Set operation failed\n");
        }
        $this->assertTrue($setResult !== false, 'Failed to set Redis value via TCP');

        // Get the value back
        $getResult = $redis->get($testKey);
        if ($getResult === $testValue) {
            Terminal::out("  \033[0;32m✓\033[0m TCP Get operation successful\n");
        } else {
            Terminal::out("  \033[0;31m✗\033[0m TCP Get operation failed\n");
        }
        $this->assertEquals($testValue, $getResult, 'Failed to get Redis value via TCP');

        // Clean up
        $redis->del($testKey);
        Terminal::out("  \033[0;32m✓\033[0m TCP Cleanup completed\n");
        Terminal::out("\n");
    }

    public function testDeletePattern()
    {
        // Test registry-based deletion against LSCWP v7.x opaque keys
        if (!extension_loaded('redis')) {
            $this->fail('Redis extension is not loaded. Install with: pecl install redis && echo "extension=redis.so" >> php.ini');
        }

        Terminal::out("\n\033[1;35m🔴 Cache Delete Pattern Test\033[0m\n");

        // Get Redis connection
        $redis = $this->getRedisConnection();
        if ($redis === false) {
            Terminal::out("\033[0;31m❌ Redis connection failed\033[0m\n");
            $this->fail('Redis connection failed - cannot test deletePattern');
        }

        $prefix = 'phpunit_test_pattern_';
        $registryKey = RedisAdapter::registryKeyFor($prefix);
        $suffix = time();

        Terminal::out("\033[0;36mPattern Configuration:\033[0m\n");
        Terminal::out("  \033[0;33m•\033[0m Salt (LSOC_PREFIX): \033[0;32m" . LSOC_PREFIX . "\033[0m\n");
        Terminal::out("  \033[0;33m•\033[0m Cache Prefix: \033[0;32m" . CacheKey::OBJECT_CACHE_PREFIX . "\033[0m\n");
        Terminal::out("  \033[0;33m•\033[0m Registry Key: \033[0;32m{$registryKey}\033[0m\n");

        // Simulate LSCWP v7.x storage: only the final hashed keys exist in Redis
        $finalKeys = [];
        for ($n = 1; $n <= 3; $n++) {
            $finalKey = $this->adapter->buildObjectCacheKey($prefix . $suffix . '_' . $n);
            $finalKeys[] = $finalKey;
            $this->assertNotFalse($redis->set($finalKey, 'test_value', 300), 'Failed to set test key');
            Terminal::out("  \033[0;32m✓\033[0m Set key: {$finalKey}\n");
        }

        // Record keys the same way Cache::set() does at runtime
        foreach ($finalKeys as $finalKey) {
            $this->adapter->registerKey($prefix, $finalKey);
        }

        // Verify registry holds exactly the test keys
        $members = $redis->sMembers($registryKey);
        Terminal::out("\n\033[0;36mKeys before deletion:\033[0m\n");
        Terminal::out("  \033[0;33m•\033[0m Found " . count($members) . " registered keys\n");
        $this->assertCount(3, $members, 'Registry did not record test keys');

        // Call deletePattern
        $deletedCount = $this->adapter->deletePattern([$prefix . '*']);

        Terminal::out("\n\033[0;36mDelete Pattern Result:\033[0m\n");
        Terminal::out("  \033[0;33m•\033[0m Deleted count: \033[0;32m{$deletedCount}\033[0m\n");
        $this->assertSame(3, $deletedCount, 'deletePattern did not delete the expected number of keys');

        // Verify final keys are gone and the registry was cleared
        $this->assertSame([], $redis->sMembers($registryKey), 'Registry SET was not cleared after purge');
        $this->assertSame(0, $redis->exists($finalKeys), 'Final keys still present after purge');

        Terminal::out("\n\033[0;32m✅ Delete Pattern Test Passed\033[0m\n");
    }

    public function testBuildObjectCacheKeyReplicatesLiteSpeedV7()
    {
        // Locks the _key() format of litespeed-cache/src/object-cache-wp.cls.php
        // so future LSCWP changes surface as a failing test.
        $key = 'job_grid_unit_' . time();

        $group = CacheKey::OBJECT_CACHE_PREFIX;
        $expected = LSOC_PREFIX . ':'
            . hash('sha256', '0:' . strlen($group) . ':' . $group . ':' . $key);

        $this->assertSame($expected, $this->adapter->buildObjectCacheKey($key));
    }
}