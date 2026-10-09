<?php

namespace WPLokerBJM\Services\SEO;

use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\Attributes\Injectable;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Services\Schema\SEO\JobSchemaBuilder;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * Resolves Schema.org JSON-LD for jobs.
 *
 * Supports per-id JobPosting schemas or a combined ItemList, and can resolve by
 * slug to avoid an extra post ID lookup. Owns schema caching so the GraphQL
 * transport layer stays thin.
 *
 * @phpstan-import-type JobPostingSchema from JobSchemaBuilder
 * @phpstan-import-type ItemListSchema from JobSchemaBuilder
 * @phpstan-import-type JobSchemaArgs from GraphQLRegistration
 * @phpstan-import-type JobSchemaResponse from GraphQLRegistration
 */
class JobSchemaService
{
    public function __construct(private GraphQLJobData $graphqlData) {}

    /**
     * Resolve Schema.org JSON-LD data for jobs.
     *
     * @param JobSchemaArgs $args Schema query arguments
     * @return JobSchemaResponse
     */
    public function resolveSchema(array $args): array
    {
        $ids = $args['ids'] ?? [];
        $slug = isset($args['slug']) ? trim((string) $args['slug']) : null;

        // Allow fetching schema by slug to avoid an extra lookup for the post ID.
        if (empty($ids) && $slug) {
            $post = get_page_by_path($slug, 'OBJECT', PostTypes::POST_TYPE_LOWONGAN);
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
    }
}
