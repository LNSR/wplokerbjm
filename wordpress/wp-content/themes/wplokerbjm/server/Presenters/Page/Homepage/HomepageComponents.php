<?php

namespace WPLokerBJM\Presenters\Page\Homepage;

use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Presenters\Page\Homepage\Components\SearchForm;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * @phpstan-import-type CardData from GraphQLJobData
 * @phpstan-import-type SearchFilters from JobQuery
 * @phpstan-import-type SearchJobsArgs from GraphQLRegistration
 * @phpstan-import-type SearchJobsResponse from GraphQLRegistration
 * @phpstan-import-type LoadMoreArgs from GraphQLRegistration
 * @phpstan-import-type LoadMoreResponse from GraphQLRegistration
 * @phpstan-import-type JobGridArgs from GraphQLRegistration
 * @phpstan-import-type AutoSuggestionsArgs from GraphQLRegistration
 * @phpstan-import-type CardData from GraphQLJobData
 * @phpstan-type JobGridData array{
 *     jobs: CardData[],
 *     maxNumPages?: int,
 *     context: 'latest'|'search',
 *     filters: SearchFilters,
 *     title?: string,
 *     totalJobs?: int
 * }
 * @phpstan-type JobGridResponse array{
 *     jobs: JobGridData['jobs'],
 *     total: JobGridData['totalJobs'],
 *     maxNumPages: JobGridData['maxNumPages'],
 *     filters: JobGridData['filters']
 * }
 * @phpstan-type CarouselData array{
 *    jobs: CardData[],
 *    totalJobs: int
 * }
 */
class HomepageComponents
{
    public function __construct(private JobRepository $jobRepository, private SearchForm $searchFormComponent) {}

    /**
     * Get job grid data with caching.
     *
     * Fetches paginated job listings using the provided WP_Query args and formats
     * them for grid display. Supports search and latest contexts with filtering.
     *
     * @param JobGridArgs $args Query arguments
     * @return JobGridResponse
     */
    public function getJobGridProps(array $args): array
    {
        $filters = $args['filters'] ?? [];
        $paged = $args['paged'] ?? 1;
        $context = $args['context'] ?? 'latest';
        $title = $args['title'] ?? '';
        $total_jobs = $args['total_jobs'] ?? 0;

        $cacheKey = CacheKey::JOB_GRID_PREFIX . md5(serialize([$filters, $paged, $context, $title, $total_jobs]));
        /** @var JobGridResponse|false $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $query_args = match ($context) {
            'search' => JobQuery::searchJobsArgs($filters, $paged, 99),
            default => JobQuery::latestJobsArgs($paged, 99),
        };
        $cacheKey = CacheKey::JOB_GRID_PREFIX . md5(serialize($query_args));
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $result = $this->jobRepository->queryJob($query_args);

        $jobs = $result['jobs'] ?? [];
        $jobs_query = $result['query'] ?? new \WP_Query();

        if (!$title) {
            $title = match ($context) {
                'search' => 'Hasil Pencarian',
                'latest' => 'Lowongan Terbaru',
                default => '',
            };
        }

        /** @var JobGridData $props */
        $props = [
            'jobs' => $jobs,
            'maxNumPages' => (int) $jobs_query->max_num_pages,
            'context' => $context,
            'filters' => [
                'cari' => (string) ($_GET['cari'] ?? ''),
                'lokasi' => (string) ($_GET['lokasi'] ?? ''),
                'gender' => (string) ($_GET['gender'] ?? ''),
                'pendidikan' => (string) ($_GET['pendidikan'] ?? ''),
                'sort' => (string) ($_GET['sort'] ?? 'desc'),
            ],
            'title' => $title,
            'totalJobs' => $jobs_query->found_posts,
        ];
        /** @var JobGridResponse $result */
        $result = [
            'jobs' => $props['jobs'] ?? [],
            'total' => $props['totalJobs'] ?? 0,
            'maxNumPages' => $props['maxNumPages'] ?? 0,
            'filters' => $filters,
        ];

        Cache::set($cacheKey, $result, 86400); // Cache for 1 day

        return $result;
    }


    /**
     * Get carousel jobs data with caching.
     *
     * Fetches carousel job listings using WP_Query args from JobQuery::getCarouselArgs
     * and formats them through JobRepository::queryJob.
     *
     * @return CarouselData Formatted carousel jobs data
     */
    public function getCarouselProps(): array
    {
        $cacheKey = CacheKey::CAROUSEL_JOBS;
        /** @var CarouselData|false $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $args = JobQuery::getCarouselArgs(-1);
        $query = new \WP_Query($args);

        $result = $this->jobRepository->queryJob($args);
        $jobs = $result['jobs'] ?? [];

        /** @var CarouselData $props */
        $props = [
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ];

        Cache::set($cacheKey, $props, 86400); // Cache for 1 day

        return $props;
    }
    /**
     * @param SearchJobsArgs $args
     * @return SearchJobsResponse
     */
    public function getSearchFormJobsProps(array $args): array
    {
        return $this->searchFormComponent->getSearchFormJobsProps($args);
    }

    /**
     * @param AutoSuggestionsArgs $args
     * @return string[]
     */
    public function getSearchFormJobsAutoSuggestion(array $args): array
    {
        return $this->searchFormComponent->getSearchFormJobsAutoSuggestion($args);
    }

    /**
     * @param LoadMoreArgs $args
     * @return LoadMoreResponse
     */
    public function getLoadmoreProps(array $args): array
    {
        $paged = $args['paged'] ?? 1;
        /** @var LoadMoreArgs['context'] $context */
        $context = $args['context'] ?? 'latest';
        /** @var LoadMoreArgs['filters'] $filters */
        $filters = $args['filters'] ?? [];

        if ($paged < 1) {
            throw new \Exception('Parameter "paged" must be greater than 0.');
        }

        $cacheKey = CacheKey::LOAD_MORE_PREFIX . md5(serialize([$paged, $context, $filters]));

        /** @var LoadMoreResponse|false $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== false && \is_array($cached)) {
            /** @var LoadMoreResponse $result */
            return $result = [
                ...$cached,
                'filters' => $filters
            ];
        }

        $argsQuery = match ($context) {
            'search' => JobQuery::searchJobsArgs($filters, $paged, 99),
            default => JobQuery::latestJobsArgs($paged, 99),
        };

        $result = $this->jobRepository->queryJob($argsQuery);
        /** @var CardData[] $jobs */
        $jobs = $result['jobs'] ?? [];
        $query = $result['query'] ?? new \WP_Query();

        if ($paged > $query->max_num_pages && $query->max_num_pages > 0) {
            throw new \Exception('Parameter "paged" exceeds max_num_pages.');
        }
        /** @var LoadMoreResponse $data */
        $data = [
            'jobs' => $jobs,
            'filters' => $filters,
            'total' => $query->found_posts,
            'maxNumPages' => $query->max_num_pages,
        ];
        Cache::set($cacheKey, SharedUtils::filterEmptyValues($data), 86400); // Cache for 1 day

        return $data;
    }
}
