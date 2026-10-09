<?php

namespace WPLokerBJM\Presenters\Page\Homepage\Components;

use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * @phpstan-import-type SearchFilters from GraphQLRegistration
 * @phpstan-import-type SearchJobsArgs from GraphQLRegistration
 * @phpstan-import-type SearchJobsResponse from GraphQLRegistration
 * @phpstan-import-type AutoSuggestionsArgs from GraphQLRegistration
 */
class SearchForm
{
    public function __construct(private JobRepository $jobRepository) {}

    /**
     * @param SearchJobsArgs $args
     * @return SearchJobsResponse
     */
    public function getSearchFormJobsProps(array $args): array
    {
        $filters = $args['filters'] ?? [];
        $context = $args['context'] ?? 'search';
        $searchFilters = [
            'cari' => (string) ($filters['cari'] ?? ''),
            Taxonomies::LOKASI_PEKERJAAN => (array) ($filters[Taxonomies::LOKASI_PEKERJAAN] ?? []),
            Taxonomies::GENDER => (array) ($filters[Taxonomies::GENDER] ?? []),
            Taxonomies::PENDIDIKAN => (array) ($filters[Taxonomies::PENDIDIKAN] ?? []),
            'sort' => (string) ($filters['sort']['value'] ?? 'desc'),
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

        /** @var SearchJobsResponse $data */
        $data = [
            'jobs' => $jobs,
            'filters' => $filters,
            'title' => 'Hasil Pencarian',
            'total' => $query->found_posts,
            'maxNumPages' => $query->max_num_pages,
        ];


        Cache::set($cacheKey, SharedUtils::filterEmptyValues($data), 86400); // Cache for 1 day

        return $data;
    }

    /**
     * Get search form jobs auto suggestion props.
     *
     * Fetches search form jobs auto suggestion props using the provided WP_Query args
     * and formats them for search form jobs auto suggestion.
     *
     * @param AutoSuggestionsArgs $args Query arguments
     * @return string[] Formatted search form jobs auto suggestion props
     */
    public function getSearchFormJobsAutoSuggestion(array $args): array
    {
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
                $results = array_map(static fn($post_id) => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $post_ids);
            }
        }

        $uniqueResults = array_values(array_unique($results));

        Cache::set($cacheKey, $uniqueResults, 86400); // Cache for 1 day

        return $uniqueResults;
    }
}
