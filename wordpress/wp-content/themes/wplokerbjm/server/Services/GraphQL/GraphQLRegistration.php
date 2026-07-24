<?php
namespace WPLokerBJM\Services\GraphQL;

use DI\Attribute\Injectable;
use WPLokerBJM\Controllers\GraphQL\Resolvers\{TaxonomyResolver, JobsDataResolver, ThemeDataResolver};
use WPLokerBJM\Controllers\GraphQL\Resolvers\Auth\JWTDataResolver;
use WPLokerBJM\Core\Theme\ThemeInject;
use WPLokerBJM\Services\GraphQL\GraphQLData;
use WPLokerBJM\Presenters\Components\{JobCarousel, JobGrid};
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Core\Container\Attributes\Action;
/**
 * @phpstan-import-type ThemeData from ThemeInject
 * @phpstan-import-type TaxonomyJobTerms from TaxonomyResolver
 * @phpstan-import-type TaxonomyTerms from TaxonomyResolver
 * @phpstan-import-type Filters from JobsDataResolver
 * @phpstan-import-type LoadMoreResponse from JobsDataResolver
 * @phpstan-import-type SearchJobsResponse from JobsDataResolver
 * @phpstan-import-type JWTDataShape from JWTDataResolver
 * @phpstan-import-type CarouselData from JobCarousel
 * @phpstan-import-type JobGridData from JobGrid
 * @phpstan-import-type CardData from GraphQLData
 * @phpstan-import-type JobDetailData from GraphQLData
 * @phpstan-type ArrayFilters array{cari?: string, lokasi_pekerjaan?: list<string>, gender?: list<string>, pendidikan?: list<string>, sort?: array{value?: string, label?: string}}
 * @phpstan-type AutoSuggestionsArgs array{query?: string}
 * @phpstan-type LoadMoreArgs array{paged?: int, context?: string, filters?: ArrayFilters}
 * @phpstan-type JobGridArgs array{paged?: int, context?: string, title?: string, total_jobs?: int, filters?: ArrayFilters}
 * @phpstan-type JobDetailArgs array{slug?: string}
 * @phpstan-type JobSchemaArgs array{ids?: list<int>, slug?: string, type?: string}
 * @phpstan-type SearchJobsArgs array{context?: string, filters?: ArrayFilters}
 * @phpstan-type RankMathHeadArgs array{url?: string}
 * @phpstan-type SyncBookmarkArgs array{ids?: list<int>}
 * @phpstan-type GraphQLDataType array{
 *     taxonomyTerms?: TaxonomyJobTerms,
 *     lokasiTerms?: TaxonomyTerms[],
 *     genderTerms?: TaxonomyTerms[],
 *     pendidikanTerms?: TaxonomyTerms[],
 *     autoSuggestions?: list<string>,
 *     carousel?: CarouselData,
 *     loadMore?: LoadMoreResponse,
 *     jobGrid?: JobGridData,
 *     jobDetail?: JobDetailData|array{},
 *     jobSchema?: array{schemas: list<string>},
 *     themeData?: ThemeData,
 *     searchJobs?: SearchJobsResponse,
 *     rankMathHead?: string,
 *     syncBookmark?: CardData[],
 *     jwt?: JWTDataShape,
 * }
 * @phpstan-type GraphQLArgumentType array{
 *     autoSuggestions?: AutoSuggestionsArgs,
 *     loadMore?: LoadMoreArgs,
 *     jobGrid?: JobGridArgs,
 *     jobDetail?: JobDetailArgs,
 *     jobSchema?: JobSchemaArgs,
 *     searchJobs?: SearchJobsArgs,
 *     rankMathHead?: RankMathHeadArgs,
 *     syncBookmark?: SyncBookmarkArgs,
 *     jwt?: JWTDataShape,
 * }
 */
final class GraphQLRegistration
{
    public function __construct(
        private readonly TaxonomyResolver $taxonomyResolver,
        private readonly JobsDataResolver $jobsDataResolver,
        private readonly ThemeDataResolver $themeDataResolver,
        private readonly JWTDataResolver $jwtDataResolver
    ) {
    }

    private const TYPE_ROOT_QUERY = 'RootQuery';
    private const TYPE_ROOT_MUTATION = 'RootMutation';

    private const TYPE_JSON = 'JSON';
    private const TYPE_STRING = 'String';
    private const TYPE_INT = 'Int';
    private const TYPE_BOOLEAN = 'Boolean';

    private const TYPE_SORT_OPTION = 'SortOption';
    private const TYPE_SORT_OPTION_INPUT = 'SortOptionInput';
    private const TYPE_TAXONOMY_TERMS_RESPONSE = 'TaxonomyTermsResponse';
    private const TYPE_JOB = 'Job';
    private const TYPE_JOB_SUMMARY = 'JobSummary';
    private const TYPE_JOB_CONTACTS = 'JobContacts';
    private const TYPE_CAROUSEL_RESPONSE = 'CarouselResponse';
    private const TYPE_LOAD_MORE_RESPONSE = 'LoadMoreResponse';
    private const TYPE_JOB_FILTERS = 'JobFilters';
    private const TYPE_JOB_FILTERS_INPUT = 'JobFiltersInput';
    private const TYPE_JOB_GRID_RESPONSE = 'JobGridResponse';
    private const TYPE_JOB_SCHEMA_RESPONSE = 'JobSchemaResponse';
    private const TYPE_LOGO = 'Logo';
    private const TYPE_THEME_DATA = 'ThemeData';
    private const TYPE_SEARCH_JOBS_RESPONSE = 'SearchJobsResponse';
    private const TYPE_BOOKMARK_RESPONSE = 'BookmarkResponse';

    /**
     * Register all GraphQL types, fields, and mutations.
     *
     * Hooked to the 'graphql_register_types' action. Orchestrates the registration
     * of custom scalars, object types, input types, and root query/mutation fields.
     */
    #[Action('graphql_register_types', 0)]
    public function registerTypes(): void
    {
        $this->registerScalars();
        $this->registerObjectTypes();
        $this->registerInputTypes();
        $this->registerFields();
    }

    /**
     * Get shared field configuration for sort option types.
     *
     * @param string $description Description for the sort option type
     * @return array{description: string, fields: array{value: array{type: string}, label: array{type: string}}}
     */
    private function sharedSortFields($description)
    {
        return [
            'description' => $description,
            'fields' => [
                'value' => ['type' => self::TYPE_STRING],
                'label' => ['type' => self::TYPE_STRING],
            ],
        ];
    }

    /**
     * Register custom GraphQL scalar types (e.g., JSON).
     */
    private function registerScalars(): void
    {
        register_graphql_scalar(self::TYPE_JSON, [
            'description' => 'Arbitrary JSON data',
            'serialize' => static function ($value) {
                return is_string($value) ? $value : json_encode($value);
            },
            'parseValue' => static function ($value) {
                return is_string($value) ? json_decode($value, true) : $value;
            },
            'parseLiteral' => static function ($ast) {
                if ($ast instanceof \GraphQL\Language\AST\StringValueNode) {
                    return json_decode($ast->value, true);
                }
                return null;
            },
        ]);
    }

    /**
     * Register all GraphQL object types (SortOption, Job, CarouselResponse, etc.).
     */
    private function registerObjectTypes(): void
    {

        register_graphql_object_type(
            self::TYPE_SORT_OPTION,
            $this->sharedSortFields('Sort option object')
        );

        // TaxonomyTermsResponse for grouped terms
        register_graphql_object_type(self::TYPE_TAXONOMY_TERMS_RESPONSE, [
            'description' => 'Response containing taxonomy terms',
            'fields' => [
                TaxonomyResolver::LOKASI_TERMS => ['type' => self::TYPE_JSON],
                TaxonomyResolver::GENDER_TERMS => ['type' => self::TYPE_JSON],
                TaxonomyResolver::PENDIDIKAN_TERMS => ['type' => self::TYPE_JSON],
            ],
        ]);

        // Job related types
        register_graphql_object_type(self::TYPE_JOB, [
            'description' => 'A job post',
            'fields' => [
                'id' => ['type' => self::TYPE_INT],
                'title' => ['type' => self::TYPE_STRING],
                'slug' => ['type' => self::TYPE_STRING],
                CustomFields::NAMA_PERUSAHAAN => ['type' => self::TYPE_STRING],
                CustomFields::TENTANG_PERUSAHAAN => ['type' => self::TYPE_STRING],
                'ringkasanPekerjaan' => ['type' => self::TYPE_JOB_SUMMARY],
                CustomFields::DESKRIPSI_PEKERJAAN => ['type' => self::TYPE_STRING],
                CustomFields::PERSYARATAN => ['type' => self::TYPE_STRING],
                CustomFields::CARA_MELAMAR => ['type' => self::TYPE_STRING],
                CustomFields::BENEFIT => ['type' => self::TYPE_STRING],
                'contacts' => ['type' => self::TYPE_JOB_CONTACTS],
                CustomFields::SOCIAL_MEDIA => ['type' => self::TYPE_STRING],
                CustomFields::STATUS_PEKERJAAN => ['type' => self::TYPE_INT],
                'permalink' => ['type' => self::TYPE_STRING],
                'post_time' => ['type' => self::TYPE_STRING],
                'dpNonce' => ['type' => self::TYPE_STRING],
            ],
        ]);

        register_graphql_object_type(self::TYPE_JOB_SUMMARY, [
            'description' => 'Job summary information',
            'fields' => [
                Taxonomies::JENIS_PEKERJAAN => ['type' => self::TYPE_STRING],
                Taxonomies::PENDIDIKAN => ['type' => self::TYPE_STRING],
                Taxonomies::GENDER => ['type' => self::TYPE_STRING],
                Taxonomies::LOKASI_PEKERJAAN => ['type' => self::TYPE_STRING],
                CustomFields::PENGALAMAN => ['type' => self::TYPE_INT],
                CustomFields::GAJI_MINIMAL => ['type' => self::TYPE_INT],
                CustomFields::GAJI_MAKSIMAL => ['type' => self::TYPE_INT],
                CustomFields::UMUR_MIN => ['type' => self::TYPE_INT],
                CustomFields::UMUR_MAX => ['type' => self::TYPE_INT],
                CustomFields::DEADLINE => ['type' => self::TYPE_STRING],
            ],
        ]);

        register_graphql_object_type(self::TYPE_JOB_CONTACTS, [
            'description' => 'Job contact information',
            'fields' => [
                CustomFields::EMAIL_KONTAK => ['type' => self::TYPE_STRING],
                CustomFields::NOMOR_KONTAK => ['type' => self::TYPE_STRING],
                CustomFields::SITUS_KONTAK => ['type' => self::TYPE_STRING],
            ],
        ]);

        register_graphql_object_type(self::TYPE_CAROUSEL_RESPONSE, [
            'description' => 'Carousel jobs response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
                'totalJobs' => ['type' => self::TYPE_INT],
            ],
        ]);

        register_graphql_object_type(self::TYPE_LOAD_MORE_RESPONSE, [
            'description' => 'Load more jobs response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
                'filters' => ['type' => self::TYPE_JOB_FILTERS],
                'total' => ['type' => self::TYPE_INT],
                'maxNumPages' => ['type' => self::TYPE_INT],
            ],
        ]);

        register_graphql_object_type(self::TYPE_JOB_FILTERS, [
            'description' => 'Job filters',
            'fields' => [
                'cari' => ['type' => self::TYPE_STRING],
                Taxonomies::LOKASI_PEKERJAAN => ['type' => ['list_of' => self::TYPE_STRING]],
                Taxonomies::GENDER => ['type' => ['list_of' => self::TYPE_STRING]],
                Taxonomies::PENDIDIKAN => ['type' => ['list_of' => self::TYPE_STRING]],
                'sort' => ['type' => self::TYPE_SORT_OPTION],
            ],
        ]);

        register_graphql_object_type(self::TYPE_JOB_GRID_RESPONSE, [
            'description' => 'Job grid response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
                'total' => ['type' => self::TYPE_INT],
                'maxNumPages' => ['type' => self::TYPE_INT],
                'filters' => ['type' => self::TYPE_JOB_FILTERS],
            ],
        ]);

        register_graphql_object_type(self::TYPE_JOB_SCHEMA_RESPONSE, [
            'description' => 'Job schema response',
            'fields' => [
                'schemas' => ['type' => ['list_of' => self::TYPE_STRING]],
            ],
        ]);


        register_graphql_object_type(self::TYPE_LOGO, [
            'description' => 'Logo image data',
            'fields' => [
                'logoUrl' => ['type' => self::TYPE_STRING],
                'logoSrcset' => ['type' => self::TYPE_STRING],
                'logoSizes' => ['type' => self::TYPE_STRING],
                'logoDecoding' => ['type' => self::TYPE_STRING],
                'logoWidth' => ['type' => self::TYPE_INT],
                'logoHeight' => ['type' => self::TYPE_INT],
            ],
        ]);

        register_graphql_object_type(self::TYPE_THEME_DATA, [
            'description' => 'Theme data object',
            'fields' => [
                'logo' => ['type' => self::TYPE_LOGO],
                'wpRestNonce' => ['type' => self::TYPE_STRING],
                'siteIconTags' => ['type' => self::TYPE_STRING],
            ],
        ]);

        register_graphql_object_type(self::TYPE_SEARCH_JOBS_RESPONSE, [
            'description' => 'Search jobs response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
                'filters' => ['type' => self::TYPE_JOB_FILTERS],
                'title' => ['type' => self::TYPE_STRING],
                'total' => ['type' => self::TYPE_INT],
                'maxNumPages' => ['type' => self::TYPE_INT],
            ],
        ]);

        register_graphql_object_type(self::TYPE_BOOKMARK_RESPONSE, [
            'description' => 'Bookmark sync response',
            'fields' => [
                'success' => ['type' => self::TYPE_BOOLEAN],
                'message' => ['type' => self::TYPE_STRING],
            ],
        ]);
    }

    /**
     * Register GraphQL input types (SortOptionInput, JobFiltersInput).
     */
    private function registerInputTypes(): void
    {
        register_graphql_input_type(self::TYPE_SORT_OPTION_INPUT, $this->sharedSortFields('Input for sort option'));

        register_graphql_input_type(self::TYPE_JOB_FILTERS_INPUT, [
            'description' => 'Input for job filters',
            'fields' => [
                'cari' => ['type' => self::TYPE_STRING],
                Taxonomies::LOKASI_PEKERJAAN => ['type' => ['list_of' => self::TYPE_STRING]],
                Taxonomies::GENDER => ['type' => ['list_of' => self::TYPE_STRING]],
                Taxonomies::PENDIDIKAN => ['type' => ['list_of' => self::TYPE_STRING]],
                'sort' => ['type' => self::TYPE_SORT_OPTION_INPUT],
            ],
        ]);
    }

    /**
     * Register root query and mutation fields with their resolvers.
     */
    private function registerFields(): void
    {
        // Root queries for taxonomy endpoints
        register_graphql_field(self::TYPE_ROOT_QUERY, 'taxonomyTerms', [
            'type' => self::TYPE_TAXONOMY_TERMS_RESPONSE,
            'description' => 'Get all taxonomy terms grouped by type',
            'resolve' => fn() => $this->taxonomyResolver->resolveAllTerms(),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'lokasiTerms', [
            'type' => self::TYPE_JSON,
            'description' => 'Get location taxonomy terms',
            'resolve' => fn() => $this->taxonomyResolver->resolveLokasiTerms(),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'genderTerms', [
            'type' => self::TYPE_JSON,
            'description' => 'Get gender taxonomy terms',
            'resolve' => fn() => $this->taxonomyResolver->resolveGenderTerms(),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'pendidikanTerms', [
            'type' => self::TYPE_JSON,
            'description' => 'Get education taxonomy terms',
            'resolve' => fn() => $this->taxonomyResolver->resolvePendidikanTerms(),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'autoSuggestions', [
            'type' => ['list_of' => self::TYPE_STRING],
            'description' => 'Get auto suggestions for job search',
            'args' => [
                'query' => [
                    'type' => self::TYPE_STRING,
                    'description' => 'The search query',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveAutoSuggestions(...$args),
        ]);

        // Jobs data queries
        register_graphql_field(self::TYPE_ROOT_QUERY, 'carousel', [
            'type' => self::TYPE_CAROUSEL_RESPONSE,
            'description' => 'Get carousel jobs data',
            'resolve' => fn() => $this->jobsDataResolver->resolveCarousel(),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'loadMore', [
            'type' => self::TYPE_LOAD_MORE_RESPONSE,
            'description' => 'Get load more jobs data',
            'args' => [
                'paged' => [
                    'type' => self::TYPE_INT,
                    'description' => 'Page number',
                    'defaultValue' => 1,
                ],
                'context' => [
                    'type' => self::TYPE_STRING,
                    'description' => 'Context for loading',
                    'defaultValue' => 'latest',
                ],
                'filters' => [
                    'type' => self::TYPE_JOB_FILTERS_INPUT,
                    'description' => 'Job filters',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveLoadMore(...$args),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'jobGrid', [
            'type' => self::TYPE_JOB_GRID_RESPONSE,
            'description' => 'Get job grid data',
            'args' => [
                'paged' => [
                    'type' => self::TYPE_INT,
                    'description' => 'Page number',
                    'defaultValue' => 1,
                ],
                'context' => [
                    'type' => self::TYPE_STRING,
                    'description' => 'Context',
                    'defaultValue' => 'latest',
                ],
                'title' => [
                    'type' => self::TYPE_STRING,
                    'description' => 'Title',
                    'defaultValue' => '',
                ],
                'total_jobs' => [
                    'type' => self::TYPE_INT,
                    'description' => 'Total jobs',
                    'defaultValue' => 0,
                ],
                'filters' => [
                    'type' => self::TYPE_JOB_FILTERS_INPUT,
                    'description' => 'Job filters',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveJobGrid(...$args),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'jobDetail', [
            'type' => self::TYPE_JOB,
            'description' => 'Get job detail',
            'args' => [
                'slug' => [
                    'type' => self::TYPE_STRING,
                    'description' => 'Job slug',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveJobDetail(...$args),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'jobSchema', [
            'type' => self::TYPE_JOB_SCHEMA_RESPONSE,
            'description' => 'Get job schema. Returns per-id JobPosting schemas by default (even for multiple IDs). Set `type` to "ItemList" to explicitly request an ItemList, or "JobPosting" to request per-id JobPosting schemas. You can also request schema by `slug` to avoid an extra lookup for the post ID.',
            'args' => [
                'ids' => [
                    'type' => ['list_of' => self::TYPE_INT],
                    'description' => 'Job IDs to retrieve. By default the resolver returns per-id JobPosting schemas; set `type` to "ItemList" to request a combined ItemList for these IDs.',
                ],
                'slug' => [
                    'type' => self::TYPE_STRING,
                    'description' => 'Optional job slug. When provided, schema is generated for the job matching this slug.',
                ],
                'type' => [
                    'type' => self::TYPE_STRING,
                    'description' => 'Optional schema type. Allowed values: "ItemList" (returns a single ItemList) or "JobPosting" (returns per-id JobPosting schemas). Defaults to per-id JobPosting behavior when omitted.',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveSchema(...$args),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'themeData', [
            'type' => self::TYPE_THEME_DATA,
            'description' => 'Get theme data',
            'resolve' => fn() => $this->themeDataResolver->resolveThemeData(),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'searchJobs', [
            'type' => self::TYPE_SEARCH_JOBS_RESPONSE,
            'description' => 'Search jobs',
            'args' => [
                'context' => [
                    'type' => self::TYPE_STRING,
                    'description' => 'Context',
                    'defaultValue' => 'search',
                ],
                'filters' => [
                    'type' => self::TYPE_JOB_FILTERS_INPUT,
                    'description' => 'Job filters',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveSearchJobs(...$args),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'rankMathHead', [
            'type' => self::TYPE_STRING,
            'description' => 'Get RankMath head data',
            'args' => [
                'url' => [
                    'type' => self::TYPE_STRING,
                    'description' => 'URL for RankMath',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveRankMathHead(...$args),
        ]);

        register_graphql_field(self::TYPE_ROOT_QUERY, 'syncBookmark', [
            'type' => ['list_of' => self::TYPE_JOB],
            'description' => 'Get bookmarked jobs by IDs',
            'args' => [
                'ids' => [
                    'type' => ['list_of' => self::TYPE_INT],
                    'description' => 'Job IDs to retrieve',
                ],
            ],
            'resolve' => fn(...$args) => $this->jobsDataResolver->resolveSyncBookmark(...$args),
        ]);

        register_graphql_field(self::TYPE_ROOT_MUTATION, 'jwt', [
            'type' => self::TYPE_STRING,
            'description' => 'Request or validate JWT token (provide username/password or existing token)',
            'args' => [
                'username' => ['type' => self::TYPE_STRING],
                'password' => ['type' => self::TYPE_STRING],
                'token' => ['type' => self::TYPE_STRING],
            ],
            'resolve' => fn(...$args) => $this->jwtDataResolver->resolveJWTorValidate(...$args),
        ]);
    }
}
