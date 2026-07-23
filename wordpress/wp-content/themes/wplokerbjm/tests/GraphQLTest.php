<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Services\GraphQL\GraphQLRegistration;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Controllers\GraphQL\Resolvers\{
    Auth\JWTDataResolver,
    JobsDataResolver,
    TaxonomyResolver,
    ThemeDataResolver,
};

class GraphQLTest extends WplokerbjmTestCase
{
    private GraphQLRegistration $graphQLRegistration;

    private const ROOT_QUERY = 'RootQuery';
    private const ROOT_MUTATION = 'RootMutation';

    public function setUp(): void
    {
        parent::setUp();

        $GLOBALS['__wplokerbjm_registered_fields'] = [];
        $GLOBALS['__wplokerbjm_registered_types'] = [];
        $GLOBALS['__wplokerbjm_registered_input_types'] = [];
        $GLOBALS['__wplokerbjm_registered_scalars'] = [];

        $resolvers = [
            TaxonomyResolver::class,
            JobsDataResolver::class,
            ThemeDataResolver::class,
            JWTDataResolver::class,
        ];

        foreach ($resolvers as $resolver) {
            $this->container()->set($resolver, $this->createMock($resolver));
        }

        \Brain\Monkey\Functions\when('register_graphql_field')->alias(function ($type, $field, $config) {
            $GLOBALS['__wplokerbjm_registered_fields'][] = ['type' => $type, 'field' => $field, 'config' => $config];
            return true;
        });

        \Brain\Monkey\Functions\when('register_graphql_object_type')->alias(function ($type, $config) {
            $GLOBALS['__wplokerbjm_registered_types'][] = ['type' => $type, 'config' => $config];
            return true;
        });

        \Brain\Monkey\Functions\when('register_graphql_input_type')->alias(function ($type, $config) {
            $GLOBALS['__wplokerbjm_registered_input_types'][] = ['type' => $type, 'config' => $config];
            return true;
        });

        \Brain\Monkey\Functions\when('register_graphql_scalar')->alias(function ($type, $config) {
            $GLOBALS['__wplokerbjm_registered_scalars'][] = ['type' => $type, 'config' => $config];
            return true;
        });

        $this->graphQLRegistration = $this->container()->get(GraphQLRegistration::class);
    }

    // ── Scalars ──────────────────────────────────────────────

    public function testScalarsRegistered(): void
    {
        $this->graphQLRegistration->registerTypes();
        $names = array_column($GLOBALS['__wplokerbjm_registered_scalars'], 'type');
        $this->assertContains('JSON', $names);
        $this->assertCount(1, $GLOBALS['__wplokerbjm_registered_scalars']);
    }

    // ── Input types ──────────────────────────────────────────

    public function testInputTypesRegistered(): void
    {
        $this->graphQLRegistration->registerTypes();
        $names = array_column($GLOBALS['__wplokerbjm_registered_input_types'], 'type');
        $this->assertContains('SortOptionInput', $names);
        $this->assertContains('JobFiltersInput', $names);
        $this->assertCount(2, $GLOBALS['__wplokerbjm_registered_input_types']);
    }

    // ── Object types ─────────────────────────────────────────

    public function testObjectTypesRegistered(): void
    {
        $this->graphQLRegistration->registerTypes();
        $names = array_column($GLOBALS['__wplokerbjm_registered_types'], 'type');
        $expected = [
            'SortOption',
            'TaxonomyTermsResponse',
            'Job',
            'JobSummary',
            'JobContacts',
            'CarouselResponse',
            'LoadMoreResponse',
            'JobFilters',
            'JobGridResponse',
            'JobSchemaResponse',
            'Logo',
            'ThemeData',
            'SearchJobsResponse',
            'BookmarkResponse',
        ];
        foreach ($expected as $t) {
            $this->assertContains($t, $names, "Type '{$t}' should be registered");
        }
        $this->assertCount(14, $GLOBALS['__wplokerbjm_registered_types']);
    }

    public function testCarouselResponseStructure(): void
    {
        $this->graphQLRegistration->registerTypes();
        $t = $this->findType('CarouselResponse');
        $this->assertSame(['list_of' => 'Job'], $t['fields']['jobs']['type']);
        $this->assertSame('Int', $t['fields']['totalJobs']['type']);
    }

    public function testThemeDataStructure(): void
    {
        $this->graphQLRegistration->registerTypes();
        $t = $this->findType('ThemeData');
        $this->assertSame('Logo', $t['fields']['logo']['type']);
        $this->assertSame('String', $t['fields']['siteIconTags']['type']);
        $this->assertSame('String', $t['fields']['wpRestNonce']['type']);
    }

    // ── Field args ───────────────────────────────────────────

    public function testLoadMoreArgs(): void
    {
        $this->graphQLRegistration->registerTypes();
        $args = $this->findField('loadMore')['config']['args'] ?? [];
        $this->assertSame('Int', $args['paged']['type']);
        $this->assertSame('JobFiltersInput', $args['filters']['type']);
    }

    public function testJobGridArgs(): void
    {
        $this->graphQLRegistration->registerTypes();
        $args = $this->findField('jobGrid')['config']['args'] ?? [];
        $this->assertSame('Int', $args['paged']['type']);
        $this->assertSame('JobFiltersInput', $args['filters']['type']);
    }

    public function testJobDetailHasSlugArg(): void
    {
        $this->graphQLRegistration->registerTypes();
        $args = $this->findField('jobDetail')['config']['args'] ?? [];
        $this->assertArrayHasKey('slug', $args);
    }

    public function testJobSchemaHasIdsAndTypeArgs(): void
    {
        $this->graphQLRegistration->registerTypes();
        $args = $this->findField('jobSchema')['config']['args'] ?? [];
        $this->assertSame(['list_of' => 'Int'], $args['ids']['type']);
        $this->assertSame('String', $args['type']['type']);
    }

    public function testJwtOnRootMutation(): void
    {
        $this->graphQLRegistration->registerTypes();
        $f = $this->findField('jwt');
        $this->assertSame(self::ROOT_MUTATION, $f['type']);
        $this->assertArrayHasKey('username', $f['config']['args'] ?? []);
        $this->assertArrayHasKey('password', $f['config']['args'] ?? []);
        $this->assertArrayHasKey('token', $f['config']['args'] ?? []);
    }

    // ── Fields (kept from original) ──────────────────────────

    public function testFieldsAreRegistered(): void
    {
        $this->graphQLRegistration->registerTypes();
        $names = array_column($GLOBALS['__wplokerbjm_registered_fields'], 'field');
        $expected = [
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
        foreach ($expected as $f) {
            $this->assertContains($f, $names);
        }
        $this->assertCount(15, $GLOBALS['__wplokerbjm_registered_fields']);
    }

    public function testFieldsOnRootQuery(): void
    {
        $this->graphQLRegistration->registerTypes();
        foreach ($GLOBALS['__wplokerbjm_registered_fields'] as $f) {
            $expected = $f['field'] === 'jwt' ? self::ROOT_MUTATION : self::ROOT_QUERY;
            $this->assertSame($expected, $f['type']);
        }
    }

    public function testFieldResolvers(): void
    {
        $this->graphQLRegistration->registerTypes();
        foreach ($GLOBALS['__wplokerbjm_registered_fields'] as $f) {
            $this->assertNotNull($f['config']['resolve'] ?? null);
        }
    }

    // ── Helpers ──────────────────────────────────────────────

    private function findField(string $name): array
    {
        foreach ($GLOBALS['__wplokerbjm_registered_fields'] as $f) {
            if ($f['field'] === $name)
                return $f;
        }
        $this->fail("Field '{$name}' not registered");
    }

    private function findType(string $name): array
    {
        foreach ($GLOBALS['__wplokerbjm_registered_types'] as $t) {
            if ($t['type'] === $name)
                return $t['config'];
        }
        $this->fail("Type '{$name}' not registered");
    }
}
