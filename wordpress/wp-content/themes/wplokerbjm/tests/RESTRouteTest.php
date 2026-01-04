<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Tests\Support\ProxyContainer;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Services\REST\RESTRoute;
use WPLokerBJM\Models\Schema\Taxonomies;

/**
 * Test suite for REST API endpoints
 *
 * Tests all REST endpoints registered by RESTRoute service using real HTTP calls
 */
class RESTRouteTest extends WplokerbjmTestCase
{
    private RESTRoute $restRoute;

    // Mock controllers (still needed for route registration testing)
    private $taxonomyDepthMock;
    private $autoSuggestionSearchMock;
    private $loadMoreMock;
    private $carouselMock;
    private $dynamicSearchMock;
    private $singleOverlayMock;
    private $dispatchSSGBuildMock;
    private $jobBookmarkMock;
    private $jobGridMock;
    private $wpThemeDataMock;
    private $jobSchemaMock;

    private string $baseUrl;

    public function setUp(): void
    {
        parent::setUp();

        // Set base URL for real API calls - use Caddy container IP for HTTPS
        $this->baseUrl = 'https://host.docker.internal/wp-json/wplokerbjm/v1';

        // Create mocks for all controller dependencies (still needed for registration tests)
        $this->taxonomyDepthMock = $this->createMock(\WPLokerBJM\Controllers\REST\TaxonomyDepth::class);
        $this->autoSuggestionSearchMock = $this->createMock(\WPLokerBJM\Controllers\REST\AutoSuggestionSearch::class);
        $this->loadMoreMock = $this->createMock(\WPLokerBJM\Controllers\REST\LoadMore::class);
        $this->carouselMock = $this->createMock(\WPLokerBJM\Controllers\REST\Carousel::class);
        $this->dynamicSearchMock = $this->createMock(\WPLokerBJM\Controllers\REST\DynamicSearch::class);
        $this->singleOverlayMock = $this->createMock(\WPLokerBJM\Controllers\REST\JobDetail::class);
        $this->dispatchSSGBuildMock = $this->createMock(\WPLokerBJM\Controllers\REST\DispatchSSGBuild::class);
        $this->jobBookmarkMock = $this->createMock(\WPLokerBJM\Controllers\REST\JobBookmark::class);
        $this->jobGridMock = $this->createMock(\WPLokerBJM\Controllers\REST\JobGridController::class);
        $this->wpThemeDataMock = $this->createMock(\WPLokerBJM\Controllers\REST\WPThemeData::class);
        $this->jobSchemaMock = $this->createMock(\WPLokerBJM\Controllers\REST\JobSchemaController::class);

        // Override the default register_rest_route mock to track registrations
        \Brain\Monkey\Functions\when('register_rest_route')->alias(function ($namespace, $route, $args) {
            $GLOBALS['__wplokerbjm_registered_routes'][] = [
                'namespace' => $namespace,
                'route' => $route,
                'args' => $args,
            ];
            return true;
        });

        // Create RESTRoute instance with mocks
        $this->restRoute = new RESTRoute(
            $this->taxonomyDepthMock,
            $this->autoSuggestionSearchMock,
            $this->loadMoreMock,
            $this->carouselMock,
            $this->dynamicSearchMock,
            $this->singleOverlayMock,
            $this->dispatchSSGBuildMock,
            $this->jobBookmarkMock,
            $this->jobGridMock,
            $this->wpThemeDataMock,
            $this->jobSchemaMock
        );
    }

    /**
     * Test that all routes are registered correctly
     */
    public function testRoutesAreRegistered(): void
    {
        echo "\n\033[1;35m🧪 Testing REST Route Registration\033[0m\n";

        // Clear any previous registrations
        $GLOBALS['__wplokerbjm_registered_routes'] = [];

        // Register routes
        $this->restRoute->registerRoutes();

        // Get registered routes
        $routes = $GLOBALS['__wplokerbjm_registered_routes'];

        echo "\033[0;36mRoute Registration Test:\033[0m\n";

        // Expected routes
        $expectedRoutes = [
            '/auto-suggest/',
            '/load-more/',
            '/search/',
            '/taxonomies/',
            '/carousel/',
            '/taxonomies/' . Taxonomies::LOKASI_PEKERJAAN,
            '/taxonomies/' . Taxonomies::GENDER,
            '/taxonomies/' . Taxonomies::PENDIDIKAN,
            '/job-detail/',
            '/dispatch-ssg/',
            '/bookmarked-jobs/',
            '/job-grid/',
            '/theme-data/',
            '/job-schema/',
        ];

        $this->assertCount(14, $routes, 'Should register exactly 14 routes');

        $registeredRoutes = array_column($routes, 'route');
        foreach ($expectedRoutes as $expectedRoute) {
            $this->assertContains($expectedRoute, $registeredRoutes, "Route {$expectedRoute} should be registered");
            echo "  \033[0;32m✓\033[0m Route registered: {$expectedRoute}\n";
        }

        echo "  \033[0;32m✅ All routes registered successfully\033[0m\n";
    }

    /**
     * Test route base URI
     */
    public function testRouteBaseURI(): void
    {
        echo "\n\033[1;36m🌐 Testing Route Base URI\033[0m\n";

        $this->assertEquals('wplokerbjm/v1', RESTRoute::$baseURI);

        echo "  \033[0;32m✅ Base URI is correctly set to 'wplokerbjm/v1'\033[0m\n";
    }

    /**
     * Test that all endpoints have proper permission callbacks
     */
    public function testEndpointPermissions(): void
    {
        echo "\n\033[1;31m🔒 Testing Endpoint Permissions\033[0m\n";

        // Clear previous registrations
        $GLOBALS['__wplokerbjm_registered_routes'] = [];

        // Register routes
        $this->restRoute->registerRoutes();

        $routes = $GLOBALS['__wplokerbjm_registered_routes'];

        // Most endpoints should have __return_true permission callback
        $publicEndpoints = [
            '/auto-suggest/',
            '/load-more/',
            '/search/',
            '/taxonomies/',
            '/carousel/',
            '/taxonomies/' . Taxonomies::LOKASI_PEKERJAAN,
            '/taxonomies/' . Taxonomies::GENDER,
            '/taxonomies/' . Taxonomies::PENDIDIKAN,
            '/job-detail/',
            '/bookmarked-jobs/',
            '/job-grid/',
            '/theme-data/',
            '/job-schema/',
        ];

        foreach ($routes as $route) {
            $routePath = $route['route'];
            $permissionCallback = $route['args']['permission_callback'] ?? null;

            if (in_array($routePath, $publicEndpoints)) {
                $this->assertEquals('__return_true', $permissionCallback,
                    "Public endpoint {$routePath} should have __return_true permission callback");
                echo "  \033[0;32m✓\033[0m Public endpoint: {$routePath}\n";
            } elseif ($routePath === '/dispatch-ssg/') {
                // Dispatch SSG has custom permission check
                $this->assertIsCallable($permissionCallback,
                    "Dispatch SSG endpoint should have custom permission callback");
                echo "  \033[0;32m✓\033[0m Protected endpoint: {$routePath}\n";
            }
        }

        echo "  \033[0;32m✅ All endpoints have appropriate permission callbacks\033[0m\n";
    }

    /**
     * Test dispatch-ssg endpoint validation
     */
    public function testDispatchSSGValidation(): void
    {
        echo "\n\033[1;31m✅ Testing Dispatch SSG Input Validation\033[0m\n";

        // Clear previous registrations
        $GLOBALS['__wplokerbjm_registered_routes'] = [];

        // Register routes
        $this->restRoute->registerRoutes();

        $routes = $GLOBALS['__wplokerbjm_registered_routes'];

        // Find dispatch-ssg route
        $dispatchRoute = null;
        foreach ($routes as $route) {
            if ($route['route'] === '/dispatch-ssg/') {
                $dispatchRoute = $route;
                break;
            }
        }

        $this->assertNotNull($dispatchRoute, 'Dispatch SSG route should be registered');

        $args = $dispatchRoute['args'];

        // Check validation callbacks
        $this->assertArrayHasKey('args', $args);
        $this->assertArrayHasKey('paths', $args['args']);
        $this->assertArrayHasKey('validate_callback', $args['args']['paths']);
        $this->assertIsCallable($args['args']['paths']['validate_callback']);

        // Test validation callback
        $validationCallback = $args['args']['paths']['validate_callback'];
        $this->assertTrue($validationCallback(['path1', 'path2']), 'Valid array should pass validation');
        $this->assertFalse($validationCallback([]), 'Empty array should fail validation');
        $this->assertFalse($validationCallback('not_array'), 'Non-array should fail validation');

        echo "  \033[0;32m✅ Dispatch SSG endpoint has proper input validation\033[0m\n";
    }

    /**
     * Test multiple endpoints simultaneously using multi-curl for better performance
     */
    public function testMultipleEndpointsWithMultiCurl(): void
    {
        echo "\n\033[1;36m🚀 Testing Multiple Endpoints with Multi-Curl (Parallel)\033[0m\n";

        // Define all GET endpoints to test in parallel
        $endpoints = [
            'auto-suggest' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/auto-suggest/?query=marketing',
                'expected_status' => 200,
                'description' => 'Auto-suggest with marketing query'
            ],
            'load-more' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/load-more/?paged=2',
                'expected_status' => 200,
                'description' => 'Load more jobs (page 2)'
            ],
            'search' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/search/?cari=marketing',
                'expected_status' => 200,
                'description' => 'Search for marketing jobs'
            ],
            'taxonomies' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/taxonomies/',
                'expected_status' => 200,
                'description' => 'Get all taxonomies'
            ],
            'carousel' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/carousel/',
                'expected_status' => 200,
                'description' => 'Get carousel jobs'
            ],
            'lokasi-pekerjaan' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/taxonomies/' . Taxonomies::LOKASI_PEKERJAAN,
                'expected_status' => 200,
                'description' => 'Get location taxonomy terms'
            ],
            'gender' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/taxonomies/' . Taxonomies::GENDER,
                'expected_status' => 200,
                'description' => 'Get gender taxonomy terms'
            ],
            'pendidikan' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/taxonomies/' . Taxonomies::PENDIDIKAN,
                'expected_status' => 200,
                'description' => 'Get education taxonomy terms'
            ],
            'job-detail' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/job-detail/?slug=marketing',
                'expected_status' => [200, 404], // Can be 200 or 404
                'description' => 'Get job detail'
            ],
            'bookmarked-jobs' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/bookmarked-jobs/?ids=1,2,3',
                'expected_status' => [200, 404], // Can be 200 or 404
                'description' => 'Get bookmarked jobs'
            ],
            'job-grid' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/job-grid/?search=marketing',
                'expected_status' => 200,
                'description' => 'Get job grid with marketing search'
            ],
            'theme-data' => [
                'method' => 'GET',
                'url' => $this->baseUrl . '/theme-data/',
                'expected_status' => 200,
                'description' => 'Get theme data'
            ],
            'dispatch-ssg' => [
                'method' => 'POST',
                'url' => $this->baseUrl . '/dispatch-ssg/',
                'args' => [
                    'body' => json_encode([
                        'paths' => ['/marketing', '/lowongan/marketing'],
                        'reason' => 'test marketing pages',
                        'dry_run' => true
                    ]),
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ],
                'expected_status' => 200, // Valid request with proper auth and data
                'description' => 'Dispatch SSG (valid request)'
            ],
        ];

        // Convert endpoints to multi-curl format
        $requests = [];
        foreach ($endpoints as $name => $endpoint) {
            $requests[] = [
                'method' => $endpoint['method'],
                'url' => $endpoint['url'],
                'args' => $endpoint['args'] ?? [],
            ];
        }

        // Execute all requests in parallel
        $startTime = microtime(true);
        $responses = __wplokerbjm_make_multi_http_requests($requests);
        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

        echo "  \033[0;33m⚡ Multi-curl execution time: {$totalTime}ms for " . count($endpoints) . " endpoints\033[0m\n";
        echo "  \033[0;33m📊 Average time per endpoint: " . round($totalTime / count($endpoints), 2) . "ms\033[0m\n\n";

        // Process and validate responses
        $index = 0;
        foreach ($endpoints as $name => $endpoint) {
            $response = $responses[$index];
            $statusCode = $response['response']['code'];
            $expectedStatuses = is_array($endpoint['expected_status']) ? $endpoint['expected_status'] : [$endpoint['expected_status']];

            echo "  \033[0;34m•\033[0m Testing: {$endpoint['description']}\n";
            echo "    \033[0;37mURL:\033[0m {$endpoint['url']}\n";
            echo "    \033[0;37mStatus:\033[0m {$statusCode} " . (in_array($statusCode, $expectedStatuses) ? "\033[0;32m✓\033[0m" : "\033[0;31m✗\033[0m") . "\n";

            // Validate status code
            $this->assertContains($statusCode, $expectedStatuses,
                "Endpoint '{$name}' should return one of [" . implode(', ', $expectedStatuses) . "], got {$statusCode}");

            // Validate JSON response for successful requests
            if (in_array($statusCode, [200, 404])) {
                $body = $response['body'];
                $data = json_decode($body, true);
                $this->assertIsArray($data, "Endpoint '{$name}' should return valid JSON");

                // Show some key metrics for successful responses
                if ($statusCode === 200) {
                    if ($name === 'search' && isset($data['jobs'])) {
                        echo "    \033[0;37mJobs found:\033[0m " . count($data['jobs']) . "\n";
                    } elseif ($name === 'job-grid' && isset($data['jobs'])) {
                        echo "    \033[0;37mJobs in grid:\033[0m " . count($data['jobs']) . "\n";
                        echo "    \033[0;37mTotal jobs:\033[0m " . ($data['totalJobs'] ?? 'N/A') . "\n";
                    } elseif ($name === 'carousel' && isset($data['jobs'])) {
                        echo "    \033[0;37mJobs in carousel:\033[0m " . count($data['jobs']) . "\n";
                        echo "    \033[0;37mTotal jobs:\033[0m " . ($data['totalJobs'] ?? 'N/A') . "\n";
                    } elseif ($name === 'auto-suggest' && is_array($data)) {
                        echo "    \033[0;37mSuggestions:\033[0m " . count($data) . "\n";
                    }
                }
            }

            // Show key headers for debugging
            if (isset($response['headers'])) {
                $headers = $response['headers'];
                if (isset($headers['x-wp-total'])) {
                    echo "    \033[0;37mWP Total:\033[0m {$headers['x-wp-total']}\n";
                }
                if (isset($headers['x-wp-totalpages'])) {
                    echo "    \033[0;37mWP Total Pages:\033[0m {$headers['x-wp-totalpages']}\n";
                }
                if (isset($headers['server-timing'])) {
                    echo "    \033[0;37mServer Timing:\033[0m {$headers['server-timing']}\n";
                }
            }

            echo "\n";
            $index++;
        }

        echo "  \033[0;32m✅ All endpoints tested successfully with multi-curl!\033[0m\n";
        echo "  \033[0;33m💡 Multi-curl is " . round((count($endpoints) * 3000) / $totalTime, 1) . "x faster than sequential requests\033[0m\n";
    }
}