<?php

namespace WPLokerBJM\Transport\GraphQL\Resolvers;


use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Services\GraphQL\Hooks\Search\SearchHooks;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Services\Schema\SEO\JobSchemaOrg;
use WPLokerBJM\Core\Container\Attributes\Injectable;
use WPLokerBJM\Presenters\Header\HeadersComponents;
use WPLokerBJM\Presenters\Page\JobDetail\JobDetailComponents;
use WPLokerBJM\Presenters\Page\Homepage\HomepageComponents;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * @phpstan-import-type CardData from GraphQLJobData
 * @phpstan-import-type JobDetailData from GraphQLJobData
 * @phpstan-import-type SearchFilters from JobQuery
 * @phpstan-import-type JobGridData from HomepageComponents
 * @phpstan-import-type CarouselData from HomepageComponents
 * @phpstan-import-type AutoSuggestionsArgs from GraphQLRegistration
 * @phpstan-import-type LoadMoreArgs from GraphQLRegistration
 * @phpstan-import-type JobGridArgs from GraphQLRegistration
 * @phpstan-import-type JobDetailArgs from GraphQLRegistration
 * @phpstan-import-type SearchJobsArgs from GraphQLRegistration
 * @phpstan-import-type SyncBookmarkArgs from GraphQLRegistration
 * @phpstan-import-type LoadMoreResponse from GraphQLRegistration
 * @phpstan-import-type SearchJobsResponse from GraphQLRegistration
 */
#[Injectable(lazy: true)]
class JobsDataResolver
{
    public function __construct(
        private readonly HomepageComponents $homepageComponentsPresenter,
        private readonly HeadersComponents $headersComponents,
        private readonly JobDetailComponents $jobDetailComponents
    ) {}

    /**
     * @return CarouselData
     */
    public function resolveCarousel(): array
    {
        try {
            return $this->homepageComponentsPresenter->getCarouselProps();
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveCarousel error: ' . $e->getMessage());
            return [
                'jobs' => [],
                'totalJobs' => 0,
            ];
        }
    }

    /**
     * Resolve load-more paginated jobs for GraphQL.
     *
     * @param mixed $root The root Query object (unused)
     * @param LoadMoreArgs $args Query arguments
     * @return LoadMoreResponse
     */
    public function resolveLoadMore($root, array $args): array
    {
        try {
            return $this->homepageComponentsPresenter->getLoadmoreProps($args);
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveLoadMore error: ' . $e->getMessage());
            return [
                'jobs' => [],
                'filters' => $args['filters'] ?? [],
                'total' => 0,
                'maxNumPages' => 0,
            ];
        }
    }

    /**
     * Resolve job grid data for GraphQL.
     *
     * @param mixed $root The root Query object (unused)
     * @param JobGridArgs $args Query arguments
     * @return JobGridData
     */
    public function resolveJobGrid($root, array $args): array
    {
        try {
            return $this->homepageComponentsPresenter->getJobGridProps($args);
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveJobGrid error: ' . $e->getMessage());
            return [
                'jobs' => [],
                'total' => 0,
                'maxNumPages' => 0,
                'filters' => $args['filters'] ?? 'latest',
            ];
        }
    }

    /**
     * Resolve single job detail for GraphQL.
     *
     * Supports published jobs by slug (default), or draft/preview access by
     * id with preview=true. Preview requires edit_post capability and bypasses
     * all caches so the latest draft content is always returned.
     *
     * @param mixed $root The root Query object (unused)
     * @param JobDetailArgs $args Query arguments with job slug or id/preview
     * @return JobDetailData|array{}
     */
    public function resolveJobDetail($root, array $args): array
    {
        try {
            return $this->jobDetailComponents->getJobDetailProps($args, current_user_can('edit_post'));
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveJobDetail error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Resolve search jobs for GraphQL.
     * @see SearchHooks::jobPostsSearchFilterImpl for hook query ['s']
     * @param mixed $root The root Query object (unused)
     * @param SearchJobsArgs $args Search filters
     * @return SearchJobsResponse
     */
    public function resolveSearchJobs($root, array $args): array
    {
        try {
            return $this->homepageComponentsPresenter->getSearchFormJobsProps($args);
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveSearchJobs error: ' . $e->getMessage());
            return [
                'jobs' => [],
                'filters' => $args['filters'] ?? [],
                'title' => 'Hasil Pencarian',
                'total' => 0,
                'maxNumPages' => 0,
            ];
        }
    }

    /**
     * Resolve bookmarked jobs by their IDs.
     *
     * @param mixed $root The root Query object (unused)
     * @param SyncBookmarkArgs $args Arguments containing job IDs
     * @return CardData[] Array of job card data for existing posts
     */
    public function resolveSyncBookmark($root, $args): array
    {
        try {
            return $this->headersComponents->getBookmarkList($args);
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveSyncBookmark error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Resolve autocomplete suggestions for job search.
     *
     * @param mixed $root The root Query object (unused)
     * @param AutoSuggestionsArgs $args Query arguments
     * @return string[] Array of unique job title suggestions
     */
    public function resolveAutoSuggestions($root, array $args): array
    {
        try {
            return $this->homepageComponentsPresenter->getSearchFormJobsAutoSuggestion($args);
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'AutoSuggestionResolver::resolveAutoSuggestions error: ' . $e->getMessage());
            return [];
        }
    }
}
