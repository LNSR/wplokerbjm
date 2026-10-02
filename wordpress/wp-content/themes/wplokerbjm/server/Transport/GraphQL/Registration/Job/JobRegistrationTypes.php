<?php

namespace WPLokerBJM\Transport\GraphQL\Registration\Job;

use WPLokerBJM\Models\Schema\{CustomFields, Taxonomies};
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;
use WPLokerBJM\Transport\GraphQL\Registration\Job\SEO\JobSchemaRegistrationTypes;
use WPLokerBJM\Transport\GraphQL\Registration\Job\Taxonomy\JobTaxonomyRegistrationTypes;
use WPLokerBJM\Transport\GraphQL\Resolvers\JobsDataResolver;
class JobRegistrationTypes
{
    public function __construct(
        private readonly JobSchemaRegistrationTypes $jobSchemaTypesRegistration,
        private readonly JobTaxonomyRegistrationTypes $jobTaxonomyTypesRegistration,
        private readonly JobsDataResolver $jobsDataResolver
    ) {}

    public const TYPE_SEARCH_JOBS_RESPONSE = 'SearchJobsResponse';
    public const TYPE_SEARCH_JOBS = 'searchJobs';
    public const TYPE_JOB = 'Job';
    public const TYPE_JOB_SUMMARY = 'JobSummary';
    public const TYPE_JOB_CONTACTS = 'JobContacts';
    public const TYPE_CAROUSEL_RESPONSE = 'CarouselResponse';
    public const TYPE_LOAD_MORE_RESPONSE = 'LoadMoreResponse';
    public const TYPE_JOB_FILTERS = 'JobFilters';
    public const TYPE_JOB_FILTERS_INPUT = 'JobFiltersInput';
    public const TYPE_JOB_GRID_RESPONSE = 'JobGridResponse';
    public const TYPE_BOOKMARK_RESPONSE = 'BookmarkResponse';

    public const TYPE_SORT_OPTION = 'SortOption';
    public const TYPE_SORT_OPTION_INPUT = 'SortOptionInput';

    public function __invoke(): void
    {
        ($this->jobSchemaTypesRegistration)();
        ($this->jobTaxonomyTypesRegistration)();
        $this->registerObjectTypes();
        $this->registerInputTypes();
        $this->registerRootFields();
    }


    private function registerObjectTypes(): void
    {

        // Job related types
        register_graphql_object_type(self::TYPE_JOB, [
            'description' => 'A job post',
            'fields' => [
                'id' => ['type' => GraphQLRegistration::TYPE_INT],
                'title' => ['type' => GraphQLRegistration::TYPE_STRING],
                'slug' => ['type' => GraphQLRegistration::TYPE_STRING],
                CustomFields::NAMA_PERUSAHAAN => ['type' => GraphQLRegistration::TYPE_STRING],
                CustomFields::TENTANG_PERUSAHAAN => ['type' => GraphQLRegistration::TYPE_STRING],
                'ringkasanPekerjaan' => ['type' => self::TYPE_JOB_SUMMARY],
                CustomFields::DESKRIPSI_PEKERJAAN => ['type' => GraphQLRegistration::TYPE_STRING],
                CustomFields::PERSYARATAN => ['type' => GraphQLRegistration::TYPE_STRING],
                CustomFields::CARA_MELAMAR => ['type' => GraphQLRegistration::TYPE_STRING],
                CustomFields::BENEFIT => ['type' => GraphQLRegistration::TYPE_STRING],
                'contacts' => ['type' => self::TYPE_JOB_CONTACTS],
                CustomFields::SOCIAL_MEDIA => ['type' => GraphQLRegistration::TYPE_STRING],
                CustomFields::STATUS_PEKERJAAN => ['type' => GraphQLRegistration::TYPE_INT],
                'permalink' => ['type' => GraphQLRegistration::TYPE_STRING],
                'post_time' => ['type' => GraphQLRegistration::TYPE_STRING],
                'dpNonce' => ['type' => GraphQLRegistration::TYPE_STRING],
            ],
        ]);

        register_graphql_object_type(self::TYPE_JOB_SUMMARY, [
            'description' => 'Job summary information',
            'fields' => [
                Taxonomies::JENIS_PEKERJAAN => ['type' => GraphQLRegistration::TYPE_STRING],
                Taxonomies::PENDIDIKAN => ['type' => GraphQLRegistration::TYPE_STRING],
                Taxonomies::GENDER => ['type' => GraphQLRegistration::TYPE_STRING],
                Taxonomies::LOKASI_PEKERJAAN => ['type' => GraphQLRegistration::TYPE_STRING],
                CustomFields::PENGALAMAN => ['type' => GraphQLRegistration::TYPE_INT],
                CustomFields::GAJI_MINIMAL => ['type' => GraphQLRegistration::TYPE_INT],
                CustomFields::GAJI_MAKSIMAL => ['type' => GraphQLRegistration::TYPE_INT],
                CustomFields::UMUR_MIN => ['type' => GraphQLRegistration::TYPE_INT],
                CustomFields::UMUR_MAX => ['type' => GraphQLRegistration::TYPE_INT],
                CustomFields::DEADLINE => ['type' => GraphQLRegistration::TYPE_STRING],
            ],
        ]);

        register_graphql_object_type(self::TYPE_JOB_CONTACTS, [
            'description' => 'Job contact information',
            'fields' => [
                CustomFields::EMAIL_KONTAK => ['type' => GraphQLRegistration::TYPE_STRING],
                CustomFields::NOMOR_KONTAK => ['type' => GraphQLRegistration::TYPE_STRING],
                CustomFields::SITUS_KONTAK => ['type' => GraphQLRegistration::TYPE_STRING],
            ],
        ]);

        register_graphql_object_type(self::TYPE_CAROUSEL_RESPONSE, [
            'description' => 'Carousel jobs response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
                'totalJobs' => ['type' => GraphQLRegistration::TYPE_INT],
            ],
        ]);

        register_graphql_object_type(self::TYPE_LOAD_MORE_RESPONSE, [
            'description' => 'Load more jobs response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
                'filters' => ['type' => self::TYPE_JOB_FILTERS],
                'total' => ['type' => GraphQLRegistration::TYPE_INT],
                'maxNumPages' => ['type' => GraphQLRegistration::TYPE_INT],
            ],
        ]);

        register_graphql_object_type(self::TYPE_JOB_FILTERS, [
            'description' => 'Job filters',
            'fields' => [
                'cari' => ['type' => GraphQLRegistration::TYPE_STRING],
                Taxonomies::LOKASI_PEKERJAAN => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
                Taxonomies::GENDER => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
                Taxonomies::PENDIDIKAN => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
                'sort' => ['type' => self::TYPE_SORT_OPTION],
            ],
        ]);

        register_graphql_object_type(self::TYPE_JOB_GRID_RESPONSE, [
            'description' => 'Job grid response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
                'total' => ['type' => GraphQLRegistration::TYPE_INT],
                'maxNumPages' => ['type' => GraphQLRegistration::TYPE_INT],
                'filters' => ['type' => self::TYPE_JOB_FILTERS],
            ],
        ]);

        register_graphql_object_type(self::TYPE_SEARCH_JOBS_RESPONSE, [
            'description' => 'Search jobs response',
            'fields' => [
                'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
                'filters' => ['type' => self::TYPE_JOB_FILTERS],
                'title' => ['type' => GraphQLRegistration::TYPE_STRING],
                'total' => ['type' => GraphQLRegistration::TYPE_INT],
                'maxNumPages' => ['type' => GraphQLRegistration::TYPE_INT],
            ],
        ]);

        register_graphql_object_type(self::TYPE_BOOKMARK_RESPONSE, [
            'description' => 'Bookmark sync response',
            'fields' => [
                'success' => ['type' => GraphQLRegistration::TYPE_BOOLEAN],
                'message' => ['type' => GraphQLRegistration::TYPE_STRING],
            ],
        ]);

        register_graphql_object_type(
            self::TYPE_SORT_OPTION,
            $this->sharedSortFields('Sort option object')
        );
    }

    private function registerInputTypes(): void
    {
        register_graphql_input_type(self::TYPE_JOB_FILTERS_INPUT, [
            'description' => 'Input for job filters',
            'fields' => [
                'cari' => ['type' => GraphQLRegistration::TYPE_STRING],
                Taxonomies::LOKASI_PEKERJAAN => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
                Taxonomies::GENDER => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
                Taxonomies::PENDIDIKAN => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
                'sort' => ['type' => self::TYPE_SORT_OPTION_INPUT],
            ],
        ]);

        register_graphql_input_type(self::TYPE_SORT_OPTION_INPUT, $this->sharedSortFields('Input for sort option'));
    }

    private function registerRootFields(): void
    {

        // Jobs data queries
        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'carousel', [
            'type' => self::TYPE_CAROUSEL_RESPONSE,
            'description' => 'Get carousel jobs data',
            'resolve' => $this->jobsDataResolver->resolveCarousel(...),
        ]);

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'loadMore', [
            'type' => self::TYPE_LOAD_MORE_RESPONSE,
            'description' => 'Get load more jobs data',
            'args' => [
                'paged' => [
                    'type' => GraphQLRegistration::TYPE_INT,
                    'description' => 'Page number',
                    'defaultValue' => 1,
                ],
                'context' => [
                    'type' => GraphQLRegistration::TYPE_STRING,
                    'description' => 'Context for loading',
                    'defaultValue' => 'latest',
                ],
                'filters' => [
                    'type' => self::TYPE_JOB_FILTERS_INPUT,
                    'description' => 'Job filters',
                ],
            ],
            'resolve' => $this->jobsDataResolver->resolveLoadMore(...),
        ]);

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'jobGrid', [
            'type' => self::TYPE_JOB_GRID_RESPONSE,
            'description' => 'Get job grid data',
            'args' => [
                'paged' => [
                    'type' => GraphQLRegistration::TYPE_INT,
                    'description' => 'Page number',
                    'defaultValue' => 1,
                ],
                'context' => [
                    'type' => GraphQLRegistration::TYPE_STRING,
                    'description' => 'Context',
                    'defaultValue' => 'latest',
                ],
                'title' => [
                    'type' => GraphQLRegistration::TYPE_STRING,
                    'description' => 'Title',
                    'defaultValue' => '',
                ],
                'total_jobs' => [
                    'type' => GraphQLRegistration::TYPE_INT,
                    'description' => 'Total jobs',
                    'defaultValue' => 0,
                ],
                'filters' => [
                    'type' => self::TYPE_JOB_FILTERS_INPUT,
                    'description' => 'Job filters',
                ],
            ],
            'resolve' => $this->jobsDataResolver->resolveJobGrid(...),
        ]);

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'jobDetail', [
            'type' => self::TYPE_JOB,
            'description' => 'Get job detail. Resolve by slug for published jobs, or by id with preview=true for draft/preview access (requires edit_post capability).',
            'args' => [
                'slug' => [
                    'type' => GraphQLRegistration::TYPE_STRING,
                    'description' => 'Job slug (published jobs)',
                ],
                'id' => [
                    'type' => GraphQLRegistration::TYPE_INT,
                    'description' => 'Job ID (used for preview of drafts)',
                ],
                'preview' => [
                    'type' => GraphQLRegistration::TYPE_BOOLEAN,
                    'description' => 'When true, allows resolving non-published posts by id. Requires the current user to have edit_post capability on the post.',
                ],
            ],
            'resolve' => $this->jobsDataResolver->resolveJobDetail(...),
        ]);

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, self::TYPE_SEARCH_JOBS, [
            'type' => self::TYPE_SEARCH_JOBS_RESPONSE,
            'description' => 'Search jobs',
            'args' => [
                'context' => [
                    'type' => GraphQLRegistration::TYPE_STRING,
                    'description' => 'Context',
                    'defaultValue' => 'search',
                ],
                'filters' => [
                    'type' => self::TYPE_JOB_FILTERS_INPUT,
                    'description' => 'Job filters',
                ],
            ],
            'resolve' => $this->jobsDataResolver->resolveSearchJobs(...),
        ]);

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'autoSuggestions', [
            'type' => ['list_of' => GraphQLRegistration::TYPE_STRING],
            'description' => 'Get auto suggestions for job search',
            'args' => [
                'query' => [
                    'type' => GraphQLRegistration::TYPE_STRING,
                    'description' => 'The search query',
                ],
            ],
            'resolve' => $this->jobsDataResolver->resolveAutoSuggestions(...),
        ]);


        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'syncBookmark', [
            'type' => ['list_of' => self::TYPE_JOB],
            'description' => 'Get bookmarked jobs by IDs',
            'args' => [
                'ids' => [
                    'type' => ['list_of' => GraphQLRegistration::TYPE_INT],
                    'description' => 'Job IDs to retrieve',
                ],
            ],
            'resolve' => $this->jobsDataResolver->resolveSyncBookmark(...),
        ]);
    }

    /**
     * Get shared field configuration for sort option types.
     *
     * @param string $description Description for the sort option type
     * @return array{description: string, fields: array{value: array{type: string}, label: array{type: string}}}
     */
    private function sharedSortFields(string $description)
    {
        return [
            'description' => $description,
            'fields' => [
                'value' => ['type' => GraphQLRegistration::TYPE_STRING],
                'label' => ['type' => GraphQLRegistration::TYPE_STRING],
            ],
        ];
    }
}
