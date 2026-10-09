<?php

namespace WPLokerBJM\Transport\GraphQL\Registration\Job;

use JobDetailArgs;
use JobDetailData;
use LoadMoreArgs;
use LoadMoreResponse;
use SearchFilters;
use SearchJobsResponse;
use WPLokerBJM\Models\Schema\{CustomFields, Taxonomies};
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;
use WPLokerBJM\Transport\GraphQL\Registration\Job\SEO\JobSchemaRegistrationTypes;
use WPLokerBJM\Transport\GraphQL\Registration\Job\Taxonomy\JobTaxonomyRegistrationTypes;
use WPLokerBJM\Transport\GraphQL\Resolvers\JobsDataResolver;
use WPLokerBJM\Presenters\Page\Homepage\HomepageComponents;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Services\GraphQL\GraphQLJobData;

/**
 * @phpstan-import-type CarouselData from HomepageComponents
 * @phpstan-import-type JobGridData from HomepageComponents
 * @phpstan-import-type JobGridResponse from HomepageComponents
 * @phpstan-import-type LoadMoreResponse from GraphQLRegistration
 * @phpstan-import-type LoadMoreData from GraphQLRegistration
 * @phpstan-import-type SearchFilters from JobQuery
 * @phpstan-import-type SearchJobsResponse from GraphQLRegistration
 * @phpstan-import-type CardData from GraphQLJobData
 * @phpstan-import-type JobDetailData from GraphQLJobData
 * @phpstan-import-type RingkasanPekerjaan from GraphQLJobData
 * @phpstan-import-type JobContacts from GraphQLJobData
 * 
 * !Args
 * @phpstan-import-type AutoSuggestionsArgs from GraphQLRegistration
 * @phpstan-import-type LoadMoreArgs from GraphQLRegistration
 * @phpstan-import-type JobGridArgs from GraphQLRegistration
 * @phpstan-import-type JobDetailArgs from GraphQLRegistration
 * @phpstan-import-type JobSchemaArgs from GraphQLRegistration
 * @phpstan-import-type RankMathHeadArgs from GraphQLRegistration
 * @phpstan-import-type SyncBookmarkArgs from GraphQLRegistration
 * @phpstan-import-type SearchJobsArgs from GraphQLRegistration
 */
class JobRegistrationTypes
{
    public function __construct(
        private readonly JobSchemaRegistrationTypes $jobSchemaTypesRegistration,
        private readonly JobTaxonomyRegistrationTypes $jobTaxonomyTypesRegistration,
        private readonly JobsDataResolver $jobsDataResolver
    ) {}

    public const string TYPE_SEARCH_JOBS_RESPONSE = 'SearchJobsResponse';
    public const string TYPE_SEARCH_JOBS = 'searchJobs';
    public const string TYPE_JOB = 'Job';
    public const string TYPE_JOB_SUMMARY = 'JobSummary';
    public const string TYPE_JOB_CONTACTS = 'JobContacts';
    public const string TYPE_CAROUSEL_RESPONSE = 'CarouselResponse';
    public const string TYPE_LOAD_MORE_RESPONSE = 'LoadMoreResponse';
    public const string TYPE_JOB_FILTERS = 'JobFilters';
    public const string TYPE_JOB_FILTERS_INPUT = 'JobFiltersInput';
    public const string TYPE_JOB_GRID_RESPONSE = 'JobGridResponse';

    public const string TYPE_SORT_OPTION = 'SortOption';
    public const string TYPE_SORT_OPTION_INPUT = 'SortOptionInput';

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
        /** @var JobDetailData $jobFieldDetail */
        $jobFieldDetail = [
            'id' => ['type' => GraphQLRegistration::TYPE_INT],
            'title' => ['type' => GraphQLRegistration::TYPE_STRING],
            'slug' => ['type' => GraphQLRegistration::TYPE_STRING],
            'nama_perusahaan' => ['type' => GraphQLRegistration::TYPE_STRING],
            'tentang_perusahaan' => ['type' => GraphQLRegistration::TYPE_STRING],
            'ringkasanPekerjaan' => ['type' => self::TYPE_JOB_SUMMARY],
            'deskripsi_pekerjaan' => ['type' => GraphQLRegistration::TYPE_STRING],
            'persyaratan' => ['type' => GraphQLRegistration::TYPE_STRING],
            'cara_melamar' => ['type' => GraphQLRegistration::TYPE_STRING],
            'benefit' => ['type' => GraphQLRegistration::TYPE_STRING],
            'contacts' => ['type' => self::TYPE_JOB_CONTACTS],
            'social_media' => ['type' => GraphQLRegistration::TYPE_STRING],
            'status_pekerjaan' => ['type' => GraphQLRegistration::TYPE_INT],
            'permalink' => ['type' => GraphQLRegistration::TYPE_STRING],
            'post_time' => ['type' => GraphQLRegistration::TYPE_STRING],
            'dpNonce' => ['type' => GraphQLRegistration::TYPE_STRING],
        ];
        // Job related types
        register_graphql_object_type(self::TYPE_JOB, [
            'description' => 'A job post',
            'fields' => $jobFieldDetail
        ]);

        /** @var RingkasanPekerjaan $jobSummaryField */
        $jobSummaryField = [
            'jenis_pekerjaan' => ['type' => GraphQLRegistration::TYPE_STRING],
            'pendidikan' => ['type' => GraphQLRegistration::TYPE_STRING],
            'gender' => ['type' => GraphQLRegistration::TYPE_STRING],
            'lokasi_pekerjaan' => ['type' => GraphQLRegistration::TYPE_STRING],
            'pengalaman' => ['type' => GraphQLRegistration::TYPE_INT],
            'gaji_minimal' => ['type' => GraphQLRegistration::TYPE_INT],
            'gaji_maksimal' => ['type' => GraphQLRegistration::TYPE_INT],
            'umur_min' => ['type' => GraphQLRegistration::TYPE_INT],
            'umur_max' => ['type' => GraphQLRegistration::TYPE_INT],
            'deadline' => ['type' => GraphQLRegistration::TYPE_STRING],
        ];

        register_graphql_object_type(self::TYPE_JOB_SUMMARY, [
            'description' => 'Job summary information',
            'fields' => $jobSummaryField,
        ]);

        /** @var JobContacts $jobContactsField */
        $jobContactsField = [
            'email_kontak' => ['type' => GraphQLRegistration::TYPE_STRING],
            'nomor_kontak' => ['type' => GraphQLRegistration::TYPE_STRING],
            'situs_kontak' => ['type' => GraphQLRegistration::TYPE_STRING],
        ];

        register_graphql_object_type(self::TYPE_JOB_CONTACTS, [
            'description' => 'Job contact information',
            'fields' => $jobContactsField,
        ]);

        /** @var CarouselData $carouselField */
        $carouselField = [
            'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
            'totalJobs' => ['type' => GraphQLRegistration::TYPE_INT],
        ];

        register_graphql_object_type(self::TYPE_CAROUSEL_RESPONSE, [
            'description' => 'Carousel jobs response',
            'fields' => $carouselField,
        ]);
        /** @var LoadMoreResponse $loadMoreField */
        $loadMoreField = [
            'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
            'filters' => ['type' => self::TYPE_JOB_FILTERS],
            'total' => ['type' => GraphQLRegistration::TYPE_INT],
            'maxNumPages' => ['type' => GraphQLRegistration::TYPE_INT],
        ];

        register_graphql_object_type(self::TYPE_LOAD_MORE_RESPONSE, [
            'description' => 'Load more jobs response',
            'fields' => $loadMoreField,
        ]);

        /** @var SearchFilters $searchFilterField */
        $searchFilterField = [
            'cari' => ['type' => GraphQLRegistration::TYPE_STRING],
            'lokasi_pekerjaan' => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
            'gender' => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
            'pendidikan' => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
            'sort' => ['type' => self::TYPE_SORT_OPTION],
        ];

        register_graphql_object_type(self::TYPE_JOB_FILTERS, [
            'description' => 'Job filters',
            'fields' => $searchFilterField,
        ]);

        /** @var JobGridResponse $jobsGridField */
        $jobsGridField = [
            'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
            'total' => ['type' => GraphQLRegistration::TYPE_INT],
            'maxNumPages' => ['type' => GraphQLRegistration::TYPE_INT],
            'filters' => ['type' => self::TYPE_JOB_FILTERS],
        ];


        register_graphql_object_type(self::TYPE_JOB_GRID_RESPONSE, [
            'description' => 'Job grid response',
            'fields' => $jobsGridField,
        ]);

        /** @var SearchJobsResponse $searchJobsField */
        $searchJobsField = [
            'jobs' => ['type' => ['list_of' => self::TYPE_JOB]],
            'filters' => ['type' => self::TYPE_JOB_FILTERS],
            'title' => ['type' => GraphQLRegistration::TYPE_STRING],
            'total' => ['type' => GraphQLRegistration::TYPE_INT],
            'maxNumPages' => ['type' => GraphQLRegistration::TYPE_INT],
        ];

        register_graphql_object_type(self::TYPE_SEARCH_JOBS_RESPONSE, [
            'description' => 'Search jobs response',
            'fields' => $searchJobsField,
        ]);

        register_graphql_object_type(
            self::TYPE_SORT_OPTION,
            $this->sharedSortFields('Sort option object')
        );
    }

    private function registerInputTypes(): void
    {

        /** @var SearchFilters $searchFiltersInputField */
        $searchFiltersInputField = [
            'cari' => ['type' => GraphQLRegistration::TYPE_STRING],
            'lokasi_pekerjaan' => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
            'gender' => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
            'pendidikan' => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
            'sort' => ['type' => self::TYPE_SORT_OPTION_INPUT],
        ];

        register_graphql_input_type(self::TYPE_JOB_FILTERS_INPUT, [
            'description' => 'Input for job filters',
            'fields' => $searchFiltersInputField,
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
        /** @var LoadMoreArgs $loadMoreArgs */
        $loadMoreArgs = [
            'paged' => [
                'type' => GraphQLRegistration::TYPE_INT,
                'description' => 'Page number',
                'defaultValue' => 1
            ],
            'context' => [
                'type' => GraphQLRegistration::TYPE_STRING,
                'description' => 'Context for loading',
                'defaultValue' => 'latest'
            ],
            'filters' => [
                'type' => self::TYPE_JOB_FILTERS_INPUT,
                'description' => 'Input for job filters'
            ],
        ];
        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'loadMore', [
            'type' => self::TYPE_LOAD_MORE_RESPONSE,
            'description' => 'Get load more jobs data',
            'args' => $loadMoreArgs,
            'resolve' => $this->jobsDataResolver->resolveLoadMore(...),
        ]);

        /** @var JobGridArgs $jobGridArgs */
        $jobGridArgs = [
            'paged' => [
                'type' => GraphQLRegistration::TYPE_INT,
                'description' => 'Page number',
                'defaultValue' => 1
            ],
            'context' => [
                'type' => GraphQLRegistration::TYPE_STRING,
                'description' => 'Context',
                'defaultValue' => 'latest'
            ],
            'title' => [
                'type' => GraphQLRegistration::TYPE_STRING,
                'description' => 'Title',
                'defaultValue' => ''
            ],
            'total_jobs' => [
                'type' => GraphQLRegistration::TYPE_INT,
                'description' => 'Total jobs',
                'defaultValue' => 0
            ],
            'filters' => [
                'type' => self::TYPE_JOB_FILTERS_INPUT,
                'description' => 'Job filters'
            ],
        ];

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'jobGrid', [
            'type' => self::TYPE_JOB_GRID_RESPONSE,
            'description' => 'Get job grid data',
            'args' => $jobGridArgs,
            'resolve' => $this->jobsDataResolver->resolveJobGrid(...),
        ]);

        /** @var JobDetailArgs $jobDetailArgs */
        $jobDetailArgs = [
            'slug' => [
                'type' => GraphQLRegistration::TYPE_STRING,
                'description' => 'Job slug (published jobs)'
            ],
            'id' => [
                'type' => GraphQLRegistration::TYPE_INT,
                'description' => 'Job ID (used for preview of drafts)'
            ],
            'preview' => [
                'type' => GraphQLRegistration::TYPE_BOOLEAN,
                'description' => 'Enable preview access (requires edit_post capability)',
                'defaultValue' => false
            ],
        ];

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'jobDetail', [
            'type' => self::TYPE_JOB,
            'description' => 'Get job detail. Resolve by slug for published jobs, or by id with preview=true for draft/preview access (requires edit_post capability).',
            'args' => $jobDetailArgs,
            'resolve' => $this->jobsDataResolver->resolveJobDetail(...),
        ]);

        /** @var SearchJobsArgs $searchJobsArgs */
        $searchJobsArgs = [
            'context' => ['type' => GraphQLRegistration::TYPE_STRING, 'description' => 'Context', 'defaultValue' => 'search'],
            'filters' => ['type' => self::TYPE_JOB_FILTERS_INPUT, 'description' => 'Job filters'],
        ];

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, self::TYPE_SEARCH_JOBS, [
            'type' => self::TYPE_SEARCH_JOBS_RESPONSE,
            'description' => 'Search jobs',
            'args' => $searchJobsArgs,
            'resolve' => $this->jobsDataResolver->resolveSearchJobs(...),
        ]);

        /** @var AutoSuggestionsArgs $autoSuggestionsArgs */
        $autoSuggestionsArgs = [
            'query' => ['type' => GraphQLRegistration::TYPE_STRING, 'description' => 'The search query'],
        ];

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'autoSuggestions', [
            'type' => ['list_of' => GraphQLRegistration::TYPE_STRING],
            'description' => 'Get auto suggestions for job search',
            'args' => $autoSuggestionsArgs,
            'resolve' => $this->jobsDataResolver->resolveAutoSuggestions(...),
        ]);

        /** @var SyncBookmarkArgs $syncBookmarkArgs */
        $syncBookmarkArgs = [
            'ids' => ['type' => ['list_of' => GraphQLRegistration::TYPE_INT], 'description' => 'Job IDs to retrieve'],
        ];

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'syncBookmark', [
            'type' => ['list_of' => self::TYPE_JOB],
            'description' => 'Get bookmarked jobs by IDs',
            'args' => $syncBookmarkArgs,
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
        /** @var SearchFilters['sort'] $searchFormSortField */
        $searchFormSortField = [
            'value' => ['type' => GraphQLRegistration::TYPE_STRING],
            'label' => ['type' => GraphQLRegistration::TYPE_STRING],
        ];

        return [
            'description' => $description,
            'fields' => $searchFormSortField,
        ];
    }
}
