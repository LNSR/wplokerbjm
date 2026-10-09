<?php

namespace WPLokerBJM\Transport\GraphQL\Registration;

use SearchFilters;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Transport\GraphQL\Resolvers\{TaxonomyResolver, JobsDataResolver, ThemeDataResolver};
use WPLokerBJM\Transport\GraphQL\Resolvers\Auth\JWTDataResolver;
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Wordpress\Theme\ThemeHooks;
use WPLokerBJM\Presenters\Page\Homepage\HomepageComponents;
use WPLokerBJM\Services\Schema\SEO\JobSchemaOrg;
use WPLokerBJM\Transport\GraphQL\Registration\Auth\JWTAuthRegistrationTypes;
use WPLokerBJM\Transport\GraphQL\Registration\Job\JobRegistrationTypes;
use WPLokerBJM\Transport\GraphQL\Registration\Theme\GraphQLThemeRegistrationTypes;

/**
 * @phpstan-import-type ThemeData from ThemeHooks
 * 
 * 
 * @phpstan-import-type JWTDataShape from JWTDataResolver
 * @phpstan-import-type CarouselData from HomepageComponents
 * @phpstan-import-type JobGridData from HomepageComponents
 * @phpstan-import-type JobGridResponse from HomepageComponents
 * @phpstan-import-type CardData from GraphQLJobData
 * @phpstan-import-type JobDetailData from GraphQLJobData
 * 
 * @phpstan-import-type JobPostingSchema from JobSchemaOrg
 * @phpstan-import-type ItemListSchema from JobSchemaOrg
 * @phpstan-type RankMathHeadArgs array{url?: string}
 * 
 * @phpstan-type LoadMoreArgs array{paged?: int, context?: 'search'|'latest', filters?: SearchFilters}
 * @phpstan-type JobGridArgs array{paged?: int, context?: 'search'|'latest', title?: string, total_jobs?: int, filters?: SearchFilters}
 * @phpstan-type JobDetailArgs array{slug?: string, id?: int, preview?: bool}
 * 
 * 
 * @phpstan-type AutoSuggestionsArgs array{query?: string}
 * @phpstan-type Context 'latest'|'search'
 * @phpstan-import-type SearchFilters from JobQuery
 * @phpstan-type SearchJobsResponse array{jobs: CardData[], filters: SearchFilters, title: 'Hasil Pencarian', total: int, maxNumPages: int}
 * @phpstan-type SearchJobsArgs array{context?: 'search'|'latest', filters?: SearchFilters}
 * 
 * @phpstan-type LoadMoreResponse array{jobs: CardData[], filters: SearchFilters, total: int, maxNumPages: int}
 * 
 * @phpstan-type JobSchemaArgs array{ids?: list<int>, slug?: string, type?: 'ItemList'|'JobPosting'}
 * @phpstan-type SyncBookmarkArgs array{ids?: list<int>}
 * @phpstan-type JobSchemaResponse array{schemas: list<JobPostingSchema>|list<ItemListSchema>}
 * 
 * @phpstan-type TaxonomyTerms array{slug: string, name: string, parent: int, children: array}
 * @phpstan-type TaxonomyJobTerms array{lokasiTerms: TaxonomyTerms[], genderTerms: TaxonomyTerms[], pendidikanTerms: TaxonomyTerms[]}
 * 
 * @phpstan-type GraphQLDataType array{
 *     taxonomyTerms?: TaxonomyJobTerms,
 *     lokasiTerms?: TaxonomyTerms[],
 *     genderTerms?: TaxonomyTerms[],
 *     pendidikanTerms?: TaxonomyTerms[],
 *     autoSuggestions?: list<string>,
 *     carousel?: CarouselData,
 *     loadMore?: LoadMoreResponse,
 *     jobGrid?: JobGridResponse,
 *     jobDetail?: JobDetailData,
 *     jobSchema?: JobSchemaResponse,
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
        private readonly JobRegistrationTypes $jobRegistrationTypes,
        private readonly GraphQLThemeRegistrationTypes $themeRegistrationTypes,
        private readonly JWTAuthRegistrationTypes $jwtAuthRegistrationTypes
    ) {}

    public const string TYPE_ROOT_QUERY = 'RootQuery';
    public const string TYPE_ROOT_MUTATION = 'RootMutation';

    public const string TYPE_JSON = 'JSON';
    public const string TYPE_STRING = 'String';
    public const string TYPE_INT = 'Int';
    public const string TYPE_BOOLEAN = 'Boolean';

    /**
     * Register all GraphQL types, fields, and mutations.
     *
     * Hooked to the 'graphql_register_types' action. Orchestrates the registration
     * of custom scalars, object types, input types, and root query/mutation fields.
     */
    #[Action('graphql_register_types', 0, tag: ['graphql'], deferRegister: true)]
    public function registerTypes(): void
    {
        $this->registerScalars();
        ($this->jobRegistrationTypes)();
        ($this->themeRegistrationTypes)();
        ($this->jwtAuthRegistrationTypes)();
    }

    /**
     * Register custom GraphQL scalar types (e.g., JSON).
     */
    private function registerScalars(): void
    {
        register_graphql_scalar(GraphQLRegistration::TYPE_JSON, [
            'description' => 'Arbitrary JSON data',
            'serialize' => static fn($value) => is_string($value) ? $value : json_encode($value),
            'parseValue' => static fn($value) => is_string($value) ? json_decode($value, true) : $value,
            'parseLiteral' => static function ($ast) {
                if ($ast instanceof \GraphQL\Language\AST\StringValueNode) {
                    return json_decode($ast->value, true);
                }
                return null;
            },
        ]);
    }
}
