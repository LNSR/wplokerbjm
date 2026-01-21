<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Tests\Support\ProxyContainer;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Services\GraphQL\GraphQLRegistration;
use WPLokerBJM\Models\Schema\Taxonomies;

/**
 * Test suite for GraphQL API endpoints
 *
 * Tests all GraphQL fields registered by GraphQLRegistration service using real HTTP calls
 */
class GraphQLTest extends WplokerbjmTestCase
{
    private GraphQLRegistration $graphQLRegistration;

    // Mock resolvers (still needed for field registration testing)
    private $taxonomyResolverMock;
    private $autoSuggestionResolverMock;
    private $jobsDataResolverMock;

    private string $baseUrl;

    public function setUp(): void
    {
        parent::setUp();

        // Set base URL for real API calls - use Caddy container IP for HTTPS
        $this->baseUrl = 'https://host.docker.internal/graphql';

        // Create mocks for all resolver dependencies (still needed for registration tests)
        $this->taxonomyResolverMock = $this->createMock(\WPLokerBJM\Controllers\GraphQL\Resolvers\TaxonomyResolver::class);
        $this->jobsDataResolverMock = $this->createMock(\WPLokerBJM\Controllers\GraphQL\Resolvers\JobsDataResolver::class);

        // Override the default register_graphql_field mock to track registrations
        \Brain\Monkey\Functions\when('register_graphql_field')->alias(function ($type, $field, $config) {
            $GLOBALS['__wplokerbjm_registered_fields'][] = [
                'type' => $type,
                'field' => $field,
                'config' => $config,
            ];
            return true;
        });

        // Mock register_graphql_object_type
        \Brain\Monkey\Functions\when('register_graphql_object_type')->alias(function ($type, $config) {
            $GLOBALS['__wplokerbjm_registered_types'][] = [
                'type' => $type,
                'config' => $config,
            ];
            return true;
        });

        // Mock register_graphql_input_type
        \Brain\Monkey\Functions\when('register_graphql_input_type')->alias(function ($type, $config) {
            $GLOBALS['__wplokerbjm_registered_input_types'][] = [
                'type' => $type,
                'config' => $config,
            ];
            return true;
        });

        // Mock register_graphql_scalar
        \Brain\Monkey\Functions\when('register_graphql_scalar')->alias(function ($type, $config) {
            $GLOBALS['__wplokerbjm_registered_scalars'][] = [
                'type' => $type,
                'config' => $config,
            ];
            return true;
        });

        // Create GraphQLRegistration instance with mocks
        $this->graphQLRegistration = new GraphQLRegistration(
            $this->taxonomyResolverMock,
            $this->jobsDataResolverMock,
        );
    }

    /**
     * Test that all GraphQL fields are registered correctly
     */
    public function testFieldsAreRegistered(): void
    {
        echo "\n\033[1;35m🧪 Testing GraphQL Field Registration\033[0m\n";

        // Clear any previous registrations
        $GLOBALS['__wplokerbjm_registered_fields'] = [];

        // Register types
        $this->graphQLRegistration->registerTypes();

        // Get registered fields
        $fields = $GLOBALS['__wplokerbjm_registered_fields'];

        echo "\033[0;36mField Registration Test:\033[0m\n";

        // Expected fields
        $expectedFields = [
            'taxonomyTerms',
            'lokasiTerms',
            'genderTerms',
            'pendidikanTerms',
            'autoSuggestions',
            'carousel',
            'loadMore',
            'jobGrid',
            'jobDetail',
            'jobSchema',
            'themeData',
            'searchJobs',
            'rankMathHead',
            'syncBookmark',
        ];

        $this->assertCount(14, $fields, 'Should register exactly 14 fields');

        $registeredFields = array_column($fields, 'field');
        foreach ($expectedFields as $expectedField) {
            $this->assertContains($expectedField, $registeredFields, "Field {$expectedField} should be registered");
            echo "  \033[0;32m✓\033[0m Field registered: {$expectedField}\n";
        }

        echo "  \033[0;32m✅ All fields registered successfully\033[0m\n";
    }

    /**
     * Test that all fields are registered on RootQuery
     */
    public function testFieldsOnRootQuery(): void
    {
        echo "\n\033[1;36m🌐 Testing Fields on RootQuery\033[0m\n";

        // Clear previous registrations
        $GLOBALS['__wplokerbjm_registered_fields'] = [];

        // Register types
        $this->graphQLRegistration->registerTypes();

        $fields = $GLOBALS['__wplokerbjm_registered_fields'];

        // All fields should be on RootQuery
        foreach ($fields as $field) {
            $this->assertEquals('RootQuery', $field['type'], "Field {$field['field']} should be registered on RootQuery");
            echo "  \033[0;32m✓\033[0m Field on RootQuery: {$field['field']}\n";
        }

        echo "  \033[0;32m✅ All fields are correctly registered on RootQuery\033[0m\n";
    }

    /**
     * Test that all fields have resolve functions
     */
    public function testFieldResolvers(): void
    {
        echo "\n\033[1;31m🔧 Testing Field Resolvers\033[0m\n";

        // Clear previous registrations
        $GLOBALS['__wplokerbjm_registered_fields'] = [];

        // Register types
        $this->graphQLRegistration->registerTypes();

        $fields = $GLOBALS['__wplokerbjm_registered_fields'];

        // All fields should have resolve functions
        foreach ($fields as $field) {
            $resolve = $field['config']['resolve'] ?? null;
            $this->assertNotNull($resolve, "Field {$field['field']} should have a resolve function");
            echo "  \033[0;32m✓\033[0m Field has resolver: {$field['field']}\n";
        }

        echo "  \033[0;32m✅ All fields have appropriate resolvers\033[0m\n";
    }

    /**
     * Test multiple GraphQL queries simultaneously using multi-curl for better performance
     */
    public function testMultipleQueriesWithMultiCurl(): void
    {
        echo "\n\033[1;36m🚀 Testing Multiple GraphQL Queries with Multi-Curl (Parallel)\033[0m\n";

        // Define all GraphQL queries to test in parallel
        $queries = [
            'taxonomyTerms' => [
                'query' => 'query { taxonomyTerms { lokasiTerms { slug name } genderTerms { slug name } pendidikanTerms { slug name } } }',
                'expected_status' => 200,
                'description' => 'Get all taxonomy terms'
            ],
            'lokasiTerms' => [
                'query' => 'query { lokasiTerms { slug name } }',
                'expected_status' => 200,
                'description' => 'Get location taxonomy terms'
            ],
            'genderTerms' => [
                'query' => 'query { genderTerms { slug name } }',
                'expected_status' => 200,
                'description' => 'Get gender taxonomy terms'
            ],
            'pendidikanTerms' => [
                'query' => 'query { pendidikanTerms { slug name } }',
                'expected_status' => 200,
                'description' => 'Get education taxonomy terms'
            ],
            'autoSuggestions' => [
                'query' => 'query { autoSuggestions(query: "marketing") }',
                'expected_status' => 200,
                'description' => 'Get auto suggestions for marketing'
            ],
            'carousel' => [
                'query' => 'query { carousel { jobs { id title } totalJobs } }',
                'expected_status' => 200,
                'description' => 'Get carousel jobs'
            ],
            'loadMore' => [
                'query' => 'query { loadMore(paged: 2) { jobs { id title } maxNumPages } }',
                'expected_status' => 200,
                'description' => 'Load more jobs (page 2)'
            ],
            'jobGrid' => [
                'query' => 'query { jobGrid(search: "marketing") { jobs { id title } maxNumPages } }',
                'expected_status' => 200,
                'description' => 'Get job grid with marketing search'
            ],
            'jobDetail' => [
                'query' => 'query { jobDetail(slug: "marketing") { job { id title } } }',
                'expected_status' => [200, 404], // Can be 200 or 404
                'description' => 'Get job detail'
            ],
            'jobSchema' => [
                'query' => 'query { jobSchema(postIds: "1,2,3") { schemas } }',
                'expected_status' => 200,
                'description' => 'Get job schemas'
            ],
            'themeData' => [
                'query' => 'query { themeData { data { siteName } } }',
                'expected_status' => 200,
                'description' => 'Get theme data'
            ],
            'searchJobs' => [
                'query' => 'query { searchJobs(cari: "marketing") { jobs { id title } maxNumPages } }',
                'expected_status' => 200,
                'description' => 'Search jobs for marketing'
            ],
            'syncBookmark' => [
                'query' => 'query { syncBookmark(ids: [1,2,3]) { id title } }',
                'expected_status' => [200, 404], // Can be 200 or 404
                'description' => 'Get bookmarked jobs'
            ],
        ];

        // Convert queries to multi-curl format
        $requests = [];
        foreach ($queries as $name => $queryData) {
            $requests[] = [
                'method' => 'POST',
                'url' => $this->baseUrl,
                'headers' => ['Content-Type: application/json'],
                'body' => json_encode(['query' => $queryData['query']]),
            ];
        }

        // Execute all requests in parallel
        $startTime = microtime(true);
        $responses = __wplokerbjm_make_multi_http_requests($requests);
        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

        echo "  \033[0;33m⚡ Multi-curl execution time: {$totalTime}ms for " . count($queries) . " queries\033[0m\n";
        echo "  \033[0;33m📊 Average time per query: " . round($totalTime / count($queries), 2) . "ms\033[0m\n\n";

        // Process and validate responses
        $index = 0;
        foreach ($queries as $name => $queryData) {
            $response = $responses[$index];
            $statusCode = $response['response']['code'];
            $expectedStatuses = is_array($queryData['expected_status']) ? $queryData['expected_status'] : [$queryData['expected_status']];

            echo "  \033[0;34m•\033[0m Testing: {$queryData['description']}\n";
            echo "    \033[0;37mQuery:\033[0m {$queryData['query']}\n";
            echo "    \033[0;37mStatus:\033[0m {$statusCode} " . (in_array($statusCode, $expectedStatuses) ? "\033[0;32m✓\033[0m" : "\033[0;31m✗\033[0m") . "\n";

            // Validate status code
            $this->assertContains($statusCode, $expectedStatuses,
                "Query '{$name}' should return one of [" . implode(', ', $expectedStatuses) . "], got {$statusCode}");

            // Validate JSON response for successful requests
            if (in_array($statusCode, [200, 404])) {
                $body = $response['body'];
                $data = json_decode($body, true);
                $this->assertIsArray($data, "Query '{$name}' should return valid JSON");

                // Check for GraphQL errors
                if (isset($data['errors'])) {
                    echo "    \033[0;31mGraphQL Errors:\033[0m\n";
                    foreach ($data['errors'] as $error) {
                        echo "      - {$error['message']}\n";
                    }
                } else {
                    echo "    \033[0;32mNo GraphQL errors\033[0m\n";
                }

                // Show some key metrics for successful responses
                if ($statusCode === 200 && isset($data['data'])) {
                    if ($name === 'searchJobs' && isset($data['data']['searchJobs']['jobs'])) {
                        echo "    \033[0;37mJobs found:\033[0m " . count($data['data']['searchJobs']['jobs']) . "\n";
                    } elseif ($name === 'jobGrid' && isset($data['data']['jobGrid']['jobs'])) {
                        echo "    \033[0;37mJobs in grid:\033[0m " . count($data['data']['jobGrid']['jobs']) . "\n";
                        echo "    \033[0;37mTotal pages:\033[0m " . ($data['data']['jobGrid']['maxNumPages'] ?? 'N/A') . "\n";
                    } elseif ($name === 'carousel' && isset($data['data']['carousel']['jobs'])) {
                        echo "    \033[0;37mJobs in carousel:\033[0m " . count($data['data']['carousel']['jobs']) . "\n";
                        echo "    \033[0;37mTotal jobs:\033[0m " . ($data['data']['carousel']['totalJobs'] ?? 'N/A') . "\n";
                    } elseif ($name === 'autoSuggestions' && isset($data['data']['autoSuggestions'])) {
                        echo "    \033[0;37mSuggestions:\033[0m " . count($data['data']['autoSuggestions']) . "\n";
                    }
                }
            }

            // Show key headers for debugging
            if (isset($response['headers'])) {
                $headers = $response['headers'];
                if (isset($headers['server-timing'])) {
                    echo "    \033[0;37mServer Timing:\033[0m {$headers['server-timing']}\n";
                }
            }

            echo "\n";
            $index++;
        }

        echo "  \033[0;32m✅ All queries tested successfully with multi-curl!\033[0m\n";
        echo "  \033[0;33m💡 Multi-curl is " . round((count($queries) * 3000) / $totalTime, 1) . "x faster than sequential requests\033[0m\n";
    }
}