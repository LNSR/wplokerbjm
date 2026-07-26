<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers;

use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Services\GraphQL\GraphQLData;
use WPLokerBJM\Services\Schema\JobSchemaOrg;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Presenters\Components\{JobCarousel, JobGrid};
use DI\Attribute\Injectable;

/**
 * @phpstan-import-type CardData from GraphQLData
 * @phpstan-import-type JobDetailData from GraphQLData
 * @phpstan-import-type JobPostingSchema from JobSchemaOrg
 * @phpstan-import-type ItemListSchema from JobSchemaOrg
 * @phpstan-import-type SearchFilters from JobQuery
 * @phpstan-import-type JobGridData from JobGrid
 * @phpstan-import-type CarouselData from JobCarousel
 * @phpstan-import-type AutoSuggestionsArgs from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
 * @phpstan-import-type LoadMoreArgs from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
 * @phpstan-import-type JobGridArgs from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
 * @phpstan-import-type JobDetailArgs from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
 * @phpstan-import-type JobSchemaArgs from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
 * @phpstan-import-type SearchJobsArgs from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
 * @phpstan-import-type RankMathHeadArgs from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
 * @phpstan-import-type SyncBookmarkArgs from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
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
        private readonly GraphQLData $graphqlData,
        private readonly JobCarousel $jobCarouselPresenter,
        private readonly JobRepository $jobRepository,
        private readonly JobGrid $jobGridPresenter,
    ) {
    }

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
            $context = $args['context'] ?? 'latest';
            $filters = $args['filters'] ?? [];

            if ($paged < 1) {
                throw new \Exception('Parameter "paged" must be greater than 0.');
            }

            $cacheKey = CacheKey::LOAD_MORE_PREFIX . md5(serialize([$paged, $context, $filters]));
            /** @var array{data: array{jobs: CardData[], filters: Filters, total: int, maxNumPages: int}, total: int, maxNumPages: int}|false $cached */
            $cached = Cache::get($cacheKey);

            if ($cached !== false) {
                return ($cached['data'] + ['filters' => $filters]) + [
                    'total' => $cached['total'],
                    'maxNumPages' => $cached['maxNumPages'],
                ];
            }

            $argsQuery = match ($context) {
                'search' => JobQuery::searchJobsArgs($filters, $paged, 99),
                default => JobQuery::latestJobsArgs($paged, 99),
            };

            $result = $this->jobRepository->queryJob($argsQuery);

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
     * @param mixed $root The root Query object (unused)
     * @param JobDetailArgs $args Query arguments with job slug
     * @return JobDetailData|array{}
     */
    public function resolveJobDetail($root, $args): array
    {
        try {
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
     * Resolve Schema.org JSON-LD data for jobs.
     *
     * Supports per-id JobPosting schemas or combined ItemList. Can also resolve
     * by slug to avoid an extra post ID lookup.
     *
     * @param mixed $root The root Query object (unused)
     * @param JobSchemaArgs $args Schema query arguments
     * @return array{schemas: array<int, string>} Array of JSON-encoded schema strings
     */
    public function resolveSchema($root, $args): array
    {
        try {
            $ids = $args['ids'] ?? [];
            $slug = isset($args['slug']) ? trim((string) $args['slug']) : null;

            // Allow fetching schema by slug to avoid an extra lookup for the post ID.
            if (empty($ids) && $slug) {
                $post = get_page_by_path($slug, 'OBJECT', 'lowongan');
                if ($post && is_object($post)) {
                    $ids = [(int) $post->ID];
                }
            }

            if (empty($ids)) {
                return ['schemas' => []];
            }

            // Normalize IDs to integers
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));
            if (empty($ids)) {
                return ['schemas' => []];
            }

            $type = isset($args['type']) ? trim((string) $args['type']) : null;

            // Require explicit request for ItemList. If not requested, return per-id JobPosting schemas
            if ($type !== 'ItemList') {
                $schemas = [];
                foreach ($ids as $id) {
                    $singleCacheKey = CacheKey::JOB_SCHEMA_PREFIX . $id;
                    /** @var JobPostingSchema|false $singleCached */
                    $singleCached = Cache::get($singleCacheKey);
                    if ($singleCached !== false) {
                        $schemas[] = json_encode($singleCached);
                        continue;
                    }

                    $schema = $this->graphqlData->JobSchema($id);
                    Cache::set($singleCacheKey, $schema, 86400);
                    $schemas[] = json_encode($schema);
                }

                return ['schemas' => $schemas];
            }

            // Build ItemList for multiple IDs, or when forced via type='ItemList'
            $cacheKey = CacheKey::GRAPHQL_JOB_SCHEMA_BATCH_PREFIX . md5(implode(',', $ids) . '|' . ($type ?? 'auto'));
            /** @var ItemListSchema|false $cached */
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return ['schemas' => [json_encode($cached)]];
            }

            // Proceed to build ItemList
            $raw = $this->graphqlData->ItemListJobPostings($ids);

            $elements = [];
            $itemListOrder = 'https://schema.org/ItemListOrderDescending';

            if (is_array($raw) && isset($raw['@type']) && $raw['@type'] === 'ItemList') {
                $itemListOrder = $raw['itemListOrder'] ?? $itemListOrder;
                $elements = is_array($raw['itemListElement']) ? array_values($raw['itemListElement']) : [];
            } elseif (is_array($raw) && array_values($raw) === $raw) {
                // Numeric array of JobPosting objects — convert to ListItem elements
                foreach ($raw as $idx => $jobPosting) {
                    $elements[] = [
                        '@type' => 'ListItem',
                        'position' => $idx + 1,
                        'item' => $jobPosting,
                    ];
                }
            } else {
                // Last-resort: try to read itemListElement
                $elements = isset($raw['itemListElement']) && is_array($raw['itemListElement']) ? array_values($raw['itemListElement']) : [];
            }

            // Ensure positions are sequential and normalized
            $mergedElements = [];
            $position = 1;
            foreach ($elements as $element) {
                $element['position'] = $position++;
                $mergedElements[] = $element;
            }

            $itemList = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => $mergedElements,
                'itemListOrder' => $itemListOrder,
                'numberOfItems' => count($mergedElements),
            ];

            Cache::set($cacheKey, $itemList, 86400);
            return ['schemas' => [json_encode($itemList)]];
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveJobSchema error: ' . $e->getMessage());
            return ['schemas' => []];
        }
    }



    /**
     * Resolve search jobs for GraphQL.
     *
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
     * Resolve RankMath head HTML for a given URL.
     *
     * Validates that the URL is internal to this site before fetching.
     *
     * @param mixed $root The root Query object (unused)
     * @param RankMathHeadArgs $args Arguments containing the URL
     * @return string HTML head tags from RankMath
     */
    public function resolveRankMathHead($root, $args): string
    {
        try {
            $url = $args['url'];

            // Validate URL - must be internal
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Logger::error('GraphQL', 'Invalid URL format: ' . $url);
                throw new \Exception('Invalid URL format');
            }

            $home_url = home_url();
            if (strpos($url, $home_url) !== 0) {
                Logger::error('GraphQL', 'URL not internal. Home URL: ' . $home_url . ', Provided URL: ' . $url);
                throw new \Exception('URL must be internal to this site');
            }

            $cacheKey = CacheKey::RANKMATH_HEAD_PREFIX . md5($url);
            /** @var string|false $cached */
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $headless = new \RankMath\Rest\Headless();
            $request = new \WP_REST_Request('GET', '/rankmath/v1/getHead');
            $request->set_param('url', $url);
            $response = $headless->get_head($request);

            if (is_wp_error($response)) {
                Logger::error('GraphQL', 'REST handler error: ' . $response->get_error_message());
                throw new \Exception('Failed to fetch head data');
            }

            $data = $response->get_data();

            if (!isset($data['success']) || !$data['success']) {
                Logger::error('GraphQL', 'REST handler returned failure');
                throw new \Exception('REST handler failed');
            }

            $html = $data['head'] ?? '';

            Cache::set($cacheKey, $html, 86400); // Cache for 1 day

            return $html;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveRankMathHead error: ' . $e->getMessage());
            return '';
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