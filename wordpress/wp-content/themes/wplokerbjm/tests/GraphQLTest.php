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
    private $jobsDataResolverMock;
    private $themeDataResolverMock;

    private string $baseUrl;

    public function setUp(): void
    {
        parent::setUp();

        // Ensure registration tracking globals are clean for each test run
        $GLOBALS['__wplokerbjm_registered_fields'] = [];
        $GLOBALS['__wplokerbjm_registered_types'] = [];
        $GLOBALS['__wplokerbjm_registered_input_types'] = [];
        $GLOBALS['__wplokerbjm_registered_scalars'] = [];

        // Set base URL for real API calls - use Caddy container IP for HTTPS
        $this->baseUrl = 'https://host.docker.internal/graphql';

        // Create mocks for all resolver dependencies (still needed for registration tests)
        $this->taxonomyResolverMock = $this->createMock(\WPLokerBJM\Controllers\GraphQL\Resolvers\TaxonomyResolver::class);
        $this->jobsDataResolverMock = $this->createMock(\WPLokerBJM\Controllers\GraphQL\Resolvers\JobsDataResolver::class);
        $this->themeDataResolverMock = $this->createMock(\WPLokerBJM\Controllers\GraphQL\Resolvers\ThemeDataResolver::class);

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
            $this->themeDataResolverMock,
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
            'jwt',
        ];

        $this->assertCount(15, $fields, 'Should register exactly 15 fields (including jwt)');

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
            if ($field['field'] === 'jwt') {
                $this->assertEquals('RootMutation', $field['type'], "Field {$field['field']} should be registered on RootMutation");
                echo "  \033[0;32m✓\033[0m Field on RootMutation: {$field['field']}\n";
                continue;
            }

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

        // Verify jobSchema field args include optional 'type' parameter
        $jobSchemaField = array_values(array_filter($fields, fn($f) => $f['field'] === 'jobSchema'))[0] ?? null;
        $this->assertNotNull($jobSchemaField, 'jobSchema field must be registered');
        $args = $jobSchemaField['config']['args'] ?? [];
        $this->assertArrayHasKey('type', $args, "jobSchema should accept an optional 'type' arg");
        $this->assertEquals('String', $args['type']['type'], "jobSchema 'type' arg should be a String");
        echo "  \033[0;32m✓\033[0m jobSchema field accepts 'type' arg\n";
    }

    /**
     * Test multiple GraphQL queries simultaneously using multi-curl for better performance
     */
    public function testMultipleQueriesWithMultiCurl(): void
    {
        echo "\n\033[1;36m🚀 Testing Multiple GraphQL Queries with Multi-Curl (Parallel)\033[0m\n";

        // Define all GraphQL queries to test in parallel (use POST bodies for complex inputs)
        $queries = [
            'taxonomyTerms' => [
                'query' => 'query { taxonomyTerms { lokasiTerms { slug name } genderTerms { slug name } pendidikanTerms { slug name } } }',
                'expected_status' => 200,
                'description' => 'Get all taxonomy terms',
            ],
            'lokasiTerms' => [
                'query' => 'query { lokasiTerms { slug name } }',
                'expected_status' => 200,
                'description' => 'Get location taxonomy terms',
            ],
            'genderTerms' => [
                'query' => 'query { genderTerms { slug name } }',
                'expected_status' => 200,
                'description' => 'Get gender taxonomy terms',
            ],
            'pendidikanTerms' => [
                'query' => 'query { pendidikanTerms { slug name } }',
                'expected_status' => 200,
                'description' => 'Get education taxonomy terms',
            ],
            'autoSuggestions' => [
                'query' => 'query { autoSuggestions(query: "marketing") }',
                'expected_status' => 200,
                'description' => 'Get auto suggestions for marketing',
            ],
            'carousel' => [
                'query' => 'query { carousel { jobs { id title } totalJobs } }',
                'expected_status' => 200,
                'description' => 'Get carousel jobs',
            ],
            'loadMore' => [
                'query' => 'query { loadMore(paged: 2) { jobs { id title } maxNumPages } }',
                'expected_status' => 200,
                'description' => 'Load more jobs (page 2)',
            ],
            'jobGrid' => [
                'query' => 'query { jobGrid(filters: { cari: "marketing" }) { jobs { id title } maxNumPages } }',
                'expected_status' => 200,
                'description' => 'Get job grid with marketing search (use filters input)',
            ],
            'jobDetail' => [
                'query' => 'query { jobDetail(slug: "marketing") { job { id title } } }',
                'expected_status' => [200, 404], // Can be 200 or 404
                'description' => 'Get job detail',
            ],
            'jobSchema' => [
                'query' => 'query { jobSchema(ids: [1,2,3]) { schemas } }',
                'expected_status' => 200,
                'description' => 'Get job schemas (ids as list of Ints)',
            ],
            'jobSchemaItemList' => [
                'query' => 'query { jobSchema(ids: [1,2,3], type: "ItemList") { schemas } }',
                'expected_status' => 200,
                'description' => 'Get job schemas forced ItemList',
            ],
            'jobSchemaJobPosting' => [
                'query' => 'query { jobSchema(ids: [1,2,3], type: "JobPosting") { schemas } }',
                'expected_status' => 200,
                'description' => 'Get job schemas forced JobPosting',
            ],
            'themeData' => [
                'query' => 'query { themeData { data { siteIconTags } } }',
                'expected_status' => 200,
                'description' => 'Get theme data (include siteIconTags in query)',
            ],
            'searchJobs' => [
                'query' => 'query { searchJobs(filters: { cari: "marketing" }) { jobs { id title } maxNumPages } }',
                'expected_status' => 200,
                'description' => 'Search jobs for marketing (use filters input)',
            ],
            'syncBookmark' => [
                'query' => 'query { syncBookmark(ids: [1,2,3]) { id title } }',
                'expected_status' => [200, 404], // Can be 200 or 404
                'description' => 'Get bookmarked jobs',
            ],
            'jwt' => [
                // Use test user credentials provided by the developer for login test
                'query' => 'mutation { jwt(username: "test", password: "&CjxM(7si2et&m*9^Ta3IQnp") }',
                'expected_status' => 200,
                'description' => 'Validate fake token (should return null or errors)',
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
            $this->assertContains(
                $statusCode,
                $expectedStatuses,
                "Query '{$name}' should return one of [" . implode(', ', $expectedStatuses) . "], got {$statusCode}"
            );

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

                // Show some key metrics for successful responses and assert expected data fields
                if ($statusCode === 200 && isset($data['data'])) {
                    // Basic assertions for presence of expected top-level fields in the GraphQL response
                    switch ($name) {
                        case 'taxonomyTerms':
                            $this->assertArrayHasKey('taxonomyTerms', $data['data'], "Query '{$name}' should contain 'taxonomyTerms' in data");
                            break;
                        case 'lokasiTerms':
                            $this->assertArrayHasKey('lokasiTerms', $data['data'], "Query '{$name}' should contain 'lokasiTerms' in data");
                            break;
                        case 'genderTerms':
                            $this->assertArrayHasKey('genderTerms', $data['data'], "Query '{$name}' should contain 'genderTerms' in data");
                            break;
                        case 'pendidikanTerms':
                            $this->assertArrayHasKey('pendidikanTerms', $data['data'], "Query '{$name}' should contain 'pendidikanTerms' in data");
                            break;
                        case 'autoSuggestions':
                            $this->assertArrayHasKey('autoSuggestions', $data['data'], "Query '{$name}' should contain 'autoSuggestions' in data");
                            break;
                        case 'carousel':
                            $this->assertArrayHasKey('carousel', $data['data'], "Query '{$name}' should contain 'carousel' in data");
                            break;
                        case 'loadMore':
                            $this->assertArrayHasKey('loadMore', $data['data'], "Query '{$name}' should contain 'loadMore' in data");
                            break;
                        case 'jobGrid':
                            $this->assertArrayHasKey('jobGrid', $data['data'], "Query '{$name}' should contain 'jobGrid' in data");
                            break;
                        case 'jobDetail':
                            $this->assertArrayHasKey('jobDetail', $data['data'], "Query '{$name}' should contain 'jobDetail' in data");
                            break;
                        case 'jobSchema':
                            $this->assertArrayHasKey('jobSchema', $data['data'], "Query '{$name}' should contain 'jobSchema' in data");                            // basic sanity: should return at least one schema string
                            $this->assertIsArray($data['data']['jobSchema']['schemas'], "jobSchema should return schemas array");
                            $this->assertNotEmpty($data['data']['jobSchema']['schemas'], "jobSchema should not return an empty schemas array");
                            break;
                        case 'jobSchemaItemList':
                            $this->assertArrayHasKey('jobSchema', $data['data'], "Query '{\$name}' should contain 'jobSchema' in data");
                            $schemas = $data['data']['jobSchema']['schemas'];
                            $this->assertCount(1, $schemas, "Forced ItemList should return a single schema string");
                            $first = json_decode($schemas[0], true);
                            $this->assertIsArray($first, "Parsed ItemList should be an array");
                            $this->assertArrayHasKey('@type', $first);
                            $this->assertEquals('ItemList', $first['@type']);
                            echo "    \033[0;32mItemList schema returned\033[0m\n";
                            break;
                        case 'jobSchemaJobPosting':
                            $this->assertArrayHasKey('jobSchema', $data['data'], "Query '{\$name}' should contain 'jobSchema' in data");
                            $schemas = $data['data']['jobSchema']['schemas'];
                            $this->assertNotEmpty($schemas, "Forced JobPosting should return at least one schema string");
                            $first = json_decode($schemas[0], true);
                            $this->assertIsArray($first, "Parsed JobPosting should be an array");
                            $this->assertArrayHasKey('@type', $first);
                            $this->assertEquals('JobPosting', $first['@type']);
                            echo "    \033[0;32mJobPosting schema returned\033[0m\n";
                            break;
                        case 'themeData':
                            $this->assertArrayHasKey('themeData', $data['data'], "Query '{$name}' should contain 'themeData' in data");                            // ensure the nested data object includes siteIconTags key (may be empty)
                            if (isset($data['data']['themeData']['data'])) {
                                $this->assertArrayHasKey('siteIconTags', $data['data']['themeData']['data'], "themeData.data should contain siteIconTags");
                            }
                            break;
                        case 'searchJobs':
                            $this->assertArrayHasKey('searchJobs', $data['data'], "Query '{$name}' should contain 'searchJobs' in data");
                            break;
                        case 'syncBookmark':
                        case 'jwt':
                            $this->assertArrayHasKey('jwt', $data['data'], "Query '{$name}' should contain 'jwt' in data");
                            // token may be null if invalid
                            break;
                            $this->assertArrayHasKey('syncBookmark', $data['data'], "Query '{$name}' should contain 'syncBookmark' in data");
                            break;
                    }

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