<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers;

use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Services\Schema\JobSchemaOrg;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Presenters\Components\{JobCarousel, JobGrid};
use DI\Attribute\Injectable;
use WPLokerBJM\Services\GraphQL\GraphQLRegistration;

/**
 * @phpstan-import-type CardData from GraphQLJobData
 * @phpstan-import-type JobDetailData from GraphQLJobData
 * @phpstan-import-type SearchFilters from JobQuery
 * @phpstan-import-type JobGridData from JobGrid
 * @phpstan-import-type CarouselData from JobCarousel
 * @phpstan-import-type AutoSuggestionsArgs from GraphQLRegistration
 * @phpstan-import-type LoadMoreArgs from GraphQLRegistration
 * @phpstan-import-type JobGridArgs from GraphQLRegistration
 * @phpstan-import-type JobDetailArgs from GraphQLRegistration
 * @phpstan-import-type SearchJobsArgs from GraphQLRegistration
 * @phpstan-import-type SyncBookmarkArgs from GraphQLRegistration
 * 
 * @phpstan-type Context 'latest'|'search'
 * @phpstan-type Filters SearchFilters
 * @phpstan-type LoadMoreResponse array{jobs: CardData[], filters: Filters, total: int, maxNumPages: int}
 * @phpstan-type SearchJobsResponse array{jobs: CardData[], filters: Filters, title: string, total: int, maxNumPages: int}
 */
#[Injectable(lazy: true)]
class JobsDataResolver
{
    public function __construct(
        private readonly GraphQLJobData $graphqlData,
        private readonly JobCarousel $jobCarouselPresenter,
        private readonly JobRepository $jobRepository,
        private readonly JobGrid $jobGridPresenter,
    ) {}

    /**
     * @return CarouselData
     */
    public function resolveCarousel(): array
    {
        try {
            $props = $this->jobCarouselPresenter->getProps(); // cached internally
            $result = [
                'jobs' => $props['jobs'] ?? [],
                'totalJobs' => $props['totalJobs'] ?? 0,
            ];

            return $result;
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
            $paged = $args['paged'] ?? 1;
            /** @var LoadMoreArgs['context'] $context */
            $context = $args['context'] ?? 'latest';
            /** @var LoadMoreArgs['filters'] $filters */
            $filters = $args['filters'] ?? [];

            if ($paged < 1) {
                throw new \Exception('Parameter "paged" must be greater than 0.');
            }

            $cacheKey = CacheKey::LOAD_MORE_PREFIX . md5(serialize([$paged, $context, $filters]));
            /** @var array{data: array{jobs: CardData[], filters: Filters, total: int, maxNumPages: int}, total: int, maxNumPages: int}|false $cached */
            $cached = Cache::get($cacheKey);

            if ($cached !== false) {
                /** @var LoadMoreResponse $result */
                $result = $cached['data'] + ['filters' => $filters] + [
                    'total' => $cached['total'],
                    'maxNumPages' => $cached['maxNumPages'],
                ];
                return $result;
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

            $data = SharedUtils::filterEmptyValues([
                'jobs' => $jobs,
                'filters' => $filters,
                'total' => $query->found_posts,
                'maxNumPages' => $query->max_num_pages,
            ]);

            $cacheData = [
                'data' => $data,
                'total' => $query->found_posts,
                'maxNumPages' => $query->max_num_pages,
            ];

            Cache::set($cacheKey, $cacheData, 86400); // Cache for 1 day

            return $data;
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
            $filters = $args['filters'] ?? [];
            $paged = $args['paged'] ?? 1;
            $context = $args['context'] ?? 'latest';
            $title = $args['title'] ?? '';
            $total_jobs = $args['total_jobs'] ?? 0;

            $cacheKey = CacheKey::JOB_GRID_PREFIX . md5(serialize([$filters, $paged, $context, $title, $total_jobs]));
            /** @var JobGridData|false $cached */
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $query_args = match ($context) {
                'search' => JobQuery::searchJobsArgs($filters, $paged, 99),
                default => JobQuery::latestJobsArgs($paged, 99),
            };

            $props = $this->jobGridPresenter->getProps($query_args, $title, $context, $total_jobs);

            $result = [
                'jobs' => $props['jobs'] ?? [],
                'total' => $props['totalJobs'] ?? 0,
                'maxNumPages' => $props['maxNumPages'] ?? 0,
                'filters' => $filters,
            ];

            Cache::set($cacheKey, $result, 86400); // Cache for 1 day

            return $result;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveJobGrid error: ' . $e->getMessage());
            return [
                'jobs' => [],
                'total' => 0,
                'maxNumPages' => 0,
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
            $id = isset($args['id']) ? (int) $args['id'] : 0;
            $preview = !empty($args['preview']);

            if ($preview || $id > 0) {
                if ($id <= 0) {
                    throw new \Exception('Missing id parameter');
                }

                $post = get_post($id);
                if (!$post instanceof \WP_Post || $post->post_type !== PostTypes::POST_TYPE_LOWONGAN) {
                    throw new \Exception('Post not found');
                }

                if ($preview) {
                    // Draft/preview access: guard capability and bypass caches.
                    if (!current_user_can('edit_post', $id)) {
                        throw new \Exception('Unauthorized preview access');
                    }

                    $job = $this->graphqlData->getJobDetailData($id, true); // bypassCache
                    if (!empty($job)) {
                        // Normalize permalink to the ID-based route so the frontend
                        // side panel stays on the preview route.
                        $job['permalink'] = esc_url(home_url('/' . PostTypes::POST_TYPE_LOWONGAN . '/' . $id));
                    }

                    return $job;
                }

                // id without preview: published posts only (defense in depth).
                if ($post->post_status !== 'publish') {
                    throw new \Exception('Post not found');
                }

                return $this->graphqlData->getJobDetailData($id);
            }

            $slug = $args['slug'];
            if (!$slug) {
                throw new \Exception('Missing slug parameter');
            }

            $post = get_page_by_path($slug, 'OBJECT', 'lowongan');
            if (!$post || !is_object($post)) {
                throw new \Exception('Post not found');
            }

            $job = $this->graphqlData->getJobDetailData($post->ID); // cached internally

            return $job;
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
            $context = $args['context'] ?? 'search';
            $filters = $args['filters'] ?? [];

            $searchFilters = [
                'cari' => (string) $filters['cari'] ?? '',
                Taxonomies::LOKASI_PEKERJAAN => (array) $filters[Taxonomies::LOKASI_PEKERJAAN] ?? [],
                Taxonomies::GENDER => (array) $filters[Taxonomies::GENDER] ?? [],
                Taxonomies::PENDIDIKAN => (array) $filters[Taxonomies::PENDIDIKAN] ?? [],
                'sort' => (string) $filters['sort']['value'] ?? 'desc',
            ];

            $cacheKey = CacheKey::DYNAMIC_SEARCH_PREFIX . md5(serialize([$searchFilters, $context]));
            /** @var SearchJobsResponse|false $cached */
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $query_args = JobQuery::searchJobsArgs($searchFilters, 1, 99);

            $result = $this->jobRepository->queryJob($query_args);

            $jobs = $result['jobs'] ?? [];
            $query = $result['query'] ?? new \WP_Query();

            $data = SharedUtils::filterEmptyValues([
                'jobs' => $jobs,
                'filters' => $filters,
                'title' => 'Hasil Pencarian',
                'total' => $query->found_posts,
                'maxNumPages' => $query->max_num_pages,
            ]);

            Cache::set($cacheKey, $data, 86400); // Cache for 1 day

            return $data;
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
            $ids_param = $args['ids'] ?? [];

            if (empty($ids_param)) {
                return [];
            } elseif (!is_array($ids_param)) {
                throw new \Exception('Invalid IDs parameter.');
            }

            $ids = array_filter(array_map('intval', $ids_param));
            if (empty($ids)) {
                return [];
            } elseif (count($ids) > 10000) {
                throw new \Exception('Maximum of 10000 IDs allowed.');
            }

            sort($ids);
            $cacheKey = CacheKey::SYNC_BOOKMARK_PREFIX . md5(implode(',', $ids));
            /** @var CardData[]|false $cached */
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $args = JobQuery::allJobsIdsArgs();
            $args['post__in'] = $ids;

            $query = new \WP_Query($args);
            $existing_ids = $query->posts;

            $response = [];
            foreach ($existing_ids as $post_id) {
                $jobData = $this->graphqlData->getCardData($post_id);
                if (!empty($jobData)) {
                    $response[] = $jobData;
                }
            }

            Cache::set($cacheKey, $response, 86400); // Cache for 1 day

            return $response;
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
            $query = sanitize_text_field($args['query']);

            $cacheKey = CacheKey::AUTO_SUGGESTION_PREFIX . md5($query);
            /** @var string[]|false $cached */
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $results = [];

            if ($query && strlen($query) >= 4) {
                $args_query = JobQuery::autoSuggestionArgs($query);
                $post_ids = get_posts($args_query);

                if (!empty($post_ids) && !is_wp_error($post_ids)) {
                    $results = array_map(function ($post_id) {
                        return html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }, $post_ids);
                }
            }

            $uniqueResults = array_values(array_unique($results));

            Cache::set($cacheKey, $uniqueResults, 86400); // Cache for 1 day

            return $uniqueResults;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'AutoSuggestionResolver::resolveAutoSuggestions error: ' . $e->getMessage());
            return [];
        }
    }
}