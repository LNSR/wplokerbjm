<?php

namespace WPLokerBJM\Presenters\Page\JobDetail;

use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * @phpstan-import-type JobDetailData from GraphQLJobData
 * @phpstan-import-type JobDetailArgs from GraphQLRegistration
 */
class JobDetailComponents
{
    public function __construct(private GraphQLJobData $graphlQLJobData) {}

    /**
     * Get single job detail for GraphQL.
     *
     * Supports published jobs by slug (default), or draft/preview access by
     * id with preview=true. Preview requires edit_post capability and bypasses
     * all caches so the latest draft content is always returned.
     *
     * @param JobDetailArgs $args Query arguments
     * @return JobDetailData
     */
    public function getJobDetailProps(array $args, bool $userHasCapability = false): array
    {
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
                if (!$userHasCapability) {
                    throw new \Exception('Unauthorized preview access');
                }

                $job = $this->graphlQLJobData->getJobDetailData($id, true); // bypassCache
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

            return $this->graphlQLJobData->getJobDetailData($id);
        }

        $slug = $args['slug'];
        if (!$slug) {
            throw new \Exception('Missing slug parameter');
        }

        $post = get_page_by_path($slug, 'OBJECT', 'lowongan');
        if (!$post || !is_object($post)) {
            throw new \Exception('Post not found');
        }

        $job = $this->graphlQLJobData->getJobDetailData($post->ID); // cached internally

        return $job;
    }
}
