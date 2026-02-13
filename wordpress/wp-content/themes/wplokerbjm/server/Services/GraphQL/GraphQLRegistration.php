<?php
namespace WPLokerBJM\Services\GraphQL;

use WPLokerBJM\Controllers\GraphQL\Resolvers\{TaxonomyResolver, JobsDataResolver, ThemeDataResolver};
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Core\Container\Attributes\Action;

class GraphQLRegistration
{
    public function __construct(
        private readonly TaxonomyResolver $taxonomyResolver,
        private readonly JobsDataResolver $jobsDataResolver,
        private readonly ThemeDataResolver $themeDataResolver,
    ) {
    }

    #[Action('graphql_register_types', 0)]
    public function registerTypes(): void
    {
        $this->registerScalars();
        $this->registerObjectTypes();
        $this->registerInputTypes();
        $this->registerFields();
    }
    private function sharedSortFields($description)
    {
        return [
            'description' => $description,
            'fields' => [
                'value' => ['type' => 'String'],
                'label' => ['type' => 'String'],
            ],
        ];
    }

    private function registerScalars(): void
    {
        register_graphql_scalar('JSON', [
            'description' => 'Arbitrary JSON data',
            'serialize' => function ($value) {
                return is_string($value) ? $value : json_encode($value);
            },
            'parseValue' => function ($value) {
                return is_string($value) ? json_decode($value, true) : $value;
            },
            'parseLiteral' => function ($ast) {
                if ($ast instanceof \GraphQL\Language\AST\StringValueNode) {
                    return json_decode($ast->value, true);
                }
                return null;
            },
        ]);
    }

    private function registerObjectTypes(): void
    {

        register_graphql_object_type(
            'SortOption',
            $this->sharedSortFields('Sort option object')
        );

        // TaxonomyTermsResponse for grouped terms
        register_graphql_object_type('TaxonomyTermsResponse', [
            'description' => 'Response containing taxonomy terms',
            'fields' => [
                TaxonomyResolver::LOKASI_TERMS => ['type' => 'JSON'],
                TaxonomyResolver::GENDER_TERMS => ['type' => 'JSON'],
                TaxonomyResolver::PENDIDIKAN_TERMS => ['type' => 'JSON'],
            ],
        ]);

        // Job related types
        register_graphql_object_type('Job', [
            'description' => 'A job post',
            'fields' => [
                'id' => ['type' => 'Int'],
                'title' => ['type' => 'String'],
                'slug' => ['type' => 'String'],
                CustomFields::NAMA_PERUSAHAAN => ['type' => 'String'],
                CustomFields::TENTANG_PERUSAHAAN => ['type' => 'String'],
                'ringkasanPekerjaan' => ['type' => 'JobSummary'],
                CustomFields::DESKRIPSI_PEKERJAAN => ['type' => 'String'],
                CustomFields::PERSYARATAN => ['type' => 'String'],
                CustomFields::CARA_MELAMAR => ['type' => 'String'],
                CustomFields::BENEFIT => ['type' => 'String'],
                'contacts' => ['type' => 'JobContacts'],
                CustomFields::SOCIAL_MEDIA => ['type' => 'String'],
                CustomFields::DEADLINE => ['type' => 'String'],
                CustomFields::STATUS_PEKERJAAN => ['type' => 'String'],
                'permalink' => ['type' => 'String'],
                'post_time' => ['type' => 'String'],
                'duplicateNonce' => ['type' => 'String'],
            ],
        ]);

        register_graphql_object_type('JobSummary', [
            'description' => 'Job summary information',
            'fields' => [
                Taxonomies::JENIS_PEKERJAAN => ['type' => 'String'],
                Taxonomies::PENDIDIKAN => ['type' => 'String'],
                Taxonomies::GENDER => ['type' => 'String'],
                Taxonomies::LOKASI_PEKERJAAN => ['type' => 'String'],
                CustomFields::PENGALAMAN => ['type' => 'String'],
                CustomFields::GAJI_MINIMAL => ['type' => 'String'],
                CustomFields::GAJI_MAKSIMAL => ['type' => 'String'],
                CustomFields::UMUR_MIN => ['type' => 'Int'],
                CustomFields::UMUR_MAX => ['type' => 'Int'],
                CustomFields::DEADLINE => ['type' => 'String'],
            ],
        ]);

        register_graphql_object_type('JobContacts', [
            'description' => 'Job contact information',
            'fields' => [
                CustomFields::EMAIL_KONTAK => ['type' => 'String'],
                CustomFields::NOMOR_KONTAK => ['type' => 'String'],
                CustomFields::SITUS_KONTAK => ['type' => 'String'],
            ],
        ]);

        register_graphql_object_type('CarouselResponse', [
            'description' => 'Carousel jobs response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => 'Job']],
                'totalJobs' => ['type' => 'Int'],
            ],
        ]);

        register_graphql_object_type('LoadMoreResponse', [
            'description' => 'Load more jobs response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => 'Job']],
                'context' => ['type' => 'String'],
                'filters' => ['type' => 'JobFilters'],
                'total' => ['type' => 'Int'],
                'maxNumPages' => ['type' => 'Int'],
            ],
        ]);

        register_graphql_object_type('JobFilters', [
            'description' => 'Job filters',
            'fields' => [
                'cari' => ['type' => 'String'],
                Taxonomies::LOKASI_PEKERJAAN => ['type' => ['list_of' => 'String']],
                Taxonomies::GENDER => ['type' => ['list_of' => 'String']],
                Taxonomies::PENDIDIKAN => ['type' => ['list_of' => 'String']],
                'sort' => ['type' => 'SortOption'],
                'context' => ['type' => 'String'],
            ],
        ]);

        register_graphql_object_type('JobGridResponse', [
            'description' => 'Job grid response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => 'Job']],
                'total' => ['type' => 'Int'],
                'maxNumPages' => ['type' => 'Int'],
                'filters' => ['type' => 'JobFilters'],
            ],
        ]);

        register_graphql_object_type('JobDetailResponse', [
            'description' => 'Job detail response',
            'fields' => [
                'job' => ['type' => 'Job'],
            ],
        ]);

        register_graphql_object_type('JobSchemaResponse', [
            'description' => 'Job schema response',
            'fields' => [
                'schemas' => ['type' => ['list_of' => 'String']],
            ],
        ]);

        
        register_graphql_object_type('Logo', [
            'description' => 'Logo image data',
            'fields' => [
                'logoUrl' => ['type' => 'String'],
                'logoSrcset' => ['type' => 'String'],
                'logoSizes' => ['type' => 'String'],
                'logoDecoding' => ['type' => 'String'],
                'logoWidth' => ['type' => 'Int'],
                'logoHeight' => ['type' => 'Int'],
            ],
        ]);

        register_graphql_object_type('ThemeData', [
            'description' => 'Theme data object',
            'fields' => [
                'themeUrl' => ['type' => 'String'],
                'logo' => ['type' => 'Logo'],
                'lastJobUpdate' => ['type' => 'String'],
                'lastTaxonomyUpdate' => ['type' => 'String'],
                'themeVersion' => ['type' => 'Int'],
                'disableTracking' => ['type' => 'Boolean'],
                'wpRestNonce' => ['type' => 'String'],
            ],
        ]);

        register_graphql_object_type('ThemeDataResponse', [
            'description' => 'Theme data response',
            'fields' => [
                'data' => ['type' => 'ThemeData'],
            ],
        ]);

        register_graphql_object_type('SearchJobsResponse', [
            'description' => 'Search jobs response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => 'Job']],
                'context' => ['type' => 'String'],
                'filters' => ['type' => 'JobFilters'],
                'title' => ['type' => 'String'],
                'total' => ['type' => 'Int'],
                'maxNumPages' => ['type' => 'Int'],
            ],
        ]);

        register_graphql_object_type('BookmarkResponse', [
            'description' => 'Bookmark sync response',
            'fields' => [
                'success' => ['type' => 'Boolean'],
                'message' => ['type' => 'String'],
            ],
        ]);
    }

    private function registerInputTypes(): void
    {
        register_graphql_input_type('SortOptionInput', $this->sharedSortFields('Input for sort option'));

        register_graphql_input_type('JobFiltersInput', [
            'description' => 'Input for job filters',
            'fields' => [
                'cari' => ['type' => 'String'],
                Taxonomies::LOKASI_PEKERJAAN => ['type' => ['list_of' => 'String']],
                Taxonomies::GENDER => ['type' => ['list_of' => 'String']],
                Taxonomies::PENDIDIKAN => ['type' => ['list_of' => 'String']],
                'sort' => ['type' => 'SortOptionInput'],
                'context' => ['type' => 'String'],
            ],
        ]);
    }

    private function registerFields(): void
    {
        // Root queries for taxonomy endpoints
        register_graphql_field('RootQuery', 'taxonomyTerms', [
            'type' => 'TaxonomyTermsResponse',
            'description' => 'Get all taxonomy terms grouped by type',
            'resolve' => fn() => $this->taxonomyResolver->resolveAllTerms(),
        ]);

        register_graphql_field('RootQuery', 'lokasiTerms', [
            'type' => 'JSON',
            'description' => 'Get location taxonomy terms',
            'resolve' => fn() => $this->taxonomyResolver->resolveLokasiTerms(),
        ]);

        register_graphql_field('RootQuery', 'genderTerms', [
            'type' => 'JSON',
            'description' => 'Get gender taxonomy terms',
            'resolve' => fn() => $this->taxonomyResolver->resolveGenderTerms(),
        ]);

        register_graphql_field('RootQuery', 'pendidikanTerms', [
            'type' => 'JSON',
            'description' => 'Get education taxonomy terms',
            'resolve' => fn() => $this->taxonomyResolver->resolvePendidikanTerms(),
        ]);

        register_graphql_field('RootQuery', 'autoSuggestions', [
            'type' => ['list_of' => 'String'],
            'description' => 'Get auto suggestions for job search',
            'args' => [
                'query' => [
                    'type' => 'String',
                    'description' => 'The search query',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveAutoSuggestions(...$args),
        ]);

        // Jobs data queries
        register_graphql_field('RootQuery', 'carousel', [
            'type' => 'CarouselResponse',
            'description' => 'Get carousel jobs data',
            'resolve' => fn() => $this->jobsDataResolver->resolveCarousel(),
        ]);

        register_graphql_field('RootQuery', 'loadMore', [
            'type' => 'LoadMoreResponse',
            'description' => 'Get load more jobs data',
            'args' => [
                'paged' => [
                    'type' => 'Int',
                    'description' => 'Page number',
                    'defaultValue' => 1,
                ],
                'context' => [
                    'type' => 'String',
                    'description' => 'Context for loading',
                    'defaultValue' => 'latest',
                ],
                'filters' => [
                    'type' => 'JobFiltersInput',
                    'description' => 'Job filters',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveLoadMore(...$args),
        ]);

        register_graphql_field('RootQuery', 'jobGrid', [
            'type' => 'JobGridResponse',
            'description' => 'Get job grid data',
            'args' => [
                'paged' => [
                    'type' => 'Int',
                    'description' => 'Page number',
                    'defaultValue' => 1,
                ],
                'context' => [
                    'type' => 'String',
                    'description' => 'Context',
                    'defaultValue' => 'latest',
                ],
                'title' => [
                    'type' => 'String',
                    'description' => 'Title',
                    'defaultValue' => '',
                ],
                'total_jobs' => [
                    'type' => 'Int',
                    'description' => 'Total jobs',
                    'defaultValue' => 0,
                ],
                'filters' => [
                    'type' => 'JobFiltersInput',
                    'description' => 'Job filters',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveJobGrid(...$args),
        ]);

        register_graphql_field('RootQuery', 'jobDetail', [
            'type' => 'JobDetailResponse',
            'description' => 'Get job detail',
            'args' => [
                'slug' => [
                    'type' => 'String',
                    'description' => 'Job slug',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveJobDetail(...$args),
        ]);

        register_graphql_field('RootQuery', 'jobSchema', [
            'type' => 'JobSchemaResponse',
            'description' => 'Get job schema. Returns per-id JobPosting schemas by default (even for multiple IDs). Set `type` to "ItemList" to explicitly request an ItemList, or "JobPosting" to request per-id JobPosting schemas.',
            'args' => [
                'ids' => [
                    'type' => ['non_null' => ['list_of' => ['non_null' => 'Int']]],
                    'description' => 'Job IDs to retrieve. By default the resolver returns per-id JobPosting schemas; set `type` to "ItemList" to request a combined ItemList for these IDs.',
                ],
                'type' => [
                    'type' => 'String',
                    'description' => 'Optional schema type. Allowed values: "ItemList" (returns a single ItemList) or "JobPosting" (returns per-id JobPosting schemas). Defaults to per-id JobPosting behavior when omitted.',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveSchema(...$args),
        ]);

        register_graphql_field('RootQuery', 'themeData', [
            'type' => 'ThemeDataResponse',
            'description' => 'Get theme data',
            'resolve' => fn() => $this->themeDataResolver->resolveThemeData(),
        ]);

        register_graphql_field('RootQuery', 'searchJobs', [
            'type' => 'SearchJobsResponse',
            'description' => 'Search jobs',
            'args' => [
                'filters' => [
                    'type' => 'JobFiltersInput',
                    'description' => 'Job filters',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveSearchJobs(...$args),
        ]);

        register_graphql_field('RootQuery', 'rankMathHead', [
            'type' => 'String',
            'description' => 'Get RankMath head data',
            'args' => [
                'url' => [
                    'type' => 'String',
                    'description' => 'URL for RankMath',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveRankMathHead(...$args),
        ]);

        register_graphql_field('RootQuery', 'syncBookmark', [
            'type' => ['list_of' => 'Job'],
            'description' => 'Get bookmarked jobs by IDs',
            'args' => [
                'ids' => [
                    'type' => ['list_of' => 'Int'],
                    'description' => 'Job IDs to retrieve',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveSyncBookmark(...$args),
        ]);
    }
}