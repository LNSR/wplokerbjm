<?php

namespace WPLokerBJM\Controllers\GraphQL\Resolvers\SEO;

use JobSchemaArgs;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Services\Schema\JobSchemaOrg;
use DI\Attribute\Injectable;
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Services\GraphQL\GraphQLRegistration;

/**
 * @phpstan-import-type JobPostingSchema from JobSchemaOrg
 * @phpstan-import-type ItemListSchema from JobSchemaOrg
 * @phpstan-import-type JobSchemaArgs from GraphQLRegistration
 * @phpstan-import-type RankMathHeadArgs from GraphQLRegistration
 * @phpstan-import-type JobSchemaResponse from GraphQLRegistration
 */
#[Injectable(lazy: true)]
class SEOjobsResolver
{

    public function __construct(private GraphQLJobData $graphqlData) {}

    /**
     * Resolve RankMath head HTML for a given URL.
     *\
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
            $parsedUrl = parse_url($url);
            $queryParams = [];
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
            }

            $postId = $queryParams['p'] ?? $queryParams['preview_id'] ?? $queryParams['id'] ?? null;

            $isPreview = !empty($postId) || isset($queryParams['preview']);

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

            if (!$isPreview) {
                $cacheKey = CacheKey::RANKMATH_HEAD_PREFIX . md5($url);
                /** @var string|false $cached */
                $cached = Cache::get($cacheKey);
                if ($cached !== false) {
                    return $cached;
                }
            }

            $data = $this->rankMathResolver->getHeadlessRankMathData($url);

            $html = $data['head'] ?? '';
            if (!$isPreview) {
                Cache::set($cacheKey, $html, 86400); // Cache for 1 day
            }

            return $html;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'JobsDataResolver::resolveRankMathHead error: ' . $e->getMessage());
            return '';
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
     * @return JobSchemaResponse
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

            /** @var JobSchemaArgs['type']|null $type */
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

    /** @var static::class */
    private object $rankMathResolver {
        get => $this->rankMathResolver ??= new class() {
            public function getHeadlessRankMathData(string $url): array
            {
                if (!class_exists('\RankMath\Rest\Headless')) {
                    Logger::error('GraphQL', 'RankMath Headless class not found');
                    throw new \Exception('RankMath Headless class not found');
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

                return $data;
            }
        };
    }
}
