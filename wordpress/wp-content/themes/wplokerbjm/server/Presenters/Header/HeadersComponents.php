<?php

namespace WPLokerBJM\Presenters\Header;

use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * @phpstan-import-type CardData from GraphQLJobData
 * @phpstan-import-type SyncBookmarkArgs from GraphQLRegistration
 */
class HeadersComponents
{
    public function __construct(private GraphQLJobData $graphqlData) {}

    /**
     * @param SyncBookmarkArgs $args
     * @return array
     */
    public function getBookmarkList(array $args): array
    {

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
    }
}
