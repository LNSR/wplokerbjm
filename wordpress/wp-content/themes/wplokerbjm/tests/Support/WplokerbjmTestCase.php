<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

abstract class WplokerbjmTestCase extends TestCase
{
    private static $mockCache = [];

    protected function setUp(): void
    {
        parent::setUp();

        ProxyContainer::boot();
        ProxyContainer::resetPerTest();

        // Reset mock cache per test
        self::$mockCache = [];

        // Initialize Brain Monkey
        \Brain\Monkey\setup();

        // Mock essential WordPress functions
        \Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn(dirname(__DIR__, 2));
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'] ?? 200;
        });
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(function ($response) {
            return $response['body'] ?? '';
        });
        // Note: wp_remote_get and wp_remote_post are mocked per-test as needed to avoid conflicts
        \Brain\Monkey\Functions\when('register_rest_route')->justReturn(true);
        \Brain\Monkey\Functions\when('wp_cache_get')->alias(function ($key, $group) {
            return self::$mockCache[$key] ?? false;
        });
        \Brain\Monkey\Functions\when('wp_cache_set')->alias(function ($key, $value, $group, $expiration) {
            self::$mockCache[$key] = $value;
            return true;
        });
        \Brain\Monkey\Functions\when('wp_cache_delete')->alias(function ($key, $group) {
            unset(self::$mockCache[$key]);
            return true;
        });
        \Brain\Monkey\Functions\when('__wplokerbjm_make_multi_http_requests')->alias(function ($requests) {
            $responses = [];
            foreach ($requests as $request) {
                $responses[] = [
                    'response' => ['code' => 200],
                    'body' => '{"success": true}',
                    'headers' => ['content-type' => 'application/json'],
                ];
            }
            return $responses;
        });
    }

    protected function tearDown(): void
    {
        // Clean up Brain Monkey mocks
        \Brain\Monkey\tearDown();

        parent::tearDown();
    }

    protected function container(): ContainerInterface
    {
        return ProxyContainer::container();
    }
}
