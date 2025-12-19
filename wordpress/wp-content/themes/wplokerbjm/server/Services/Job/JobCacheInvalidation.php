<?php
namespace WPLokerBJM\Services\Job;

use WPLokerBJM\Core\Cache;
use WPLokerBJM\Factories\JobDataFactory;
use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Services\REST\RESTData;

class JobCacheInvalidation implements HooksInterface
{

    public function registerActions(): void
    {
        add_action('save_post', fn(...$args) => $this->clearJobDataCache(...$args), 10, 2);
        add_action('delete_post', fn(...$args) => $this->clearJobDataCache(...$args));
        add_action('updated_post_meta', fn(...$args) => $this->clearJobDataCacheOnMeta(...$args), 10, 4);
        add_action('set_object_terms', fn(...$args) => $this->clearJobDataCacheOnTax(...$args), 10, 6);
        add_action('transition_post_status', fn(...$args) => $this->clearJobDataCacheOnStatusChange(...$args), 10, 3);
    }

    public function registerFilters(): void
    {
        // No filters to register
    }

    /**
     * Clear job data cache when post is saved or deleted.
     */
    public function clearJobDataCache($post_id, $post = null): void
    {
        $this->invalidateJobDataCache($post_id);
    }

    /**
     * Clear job data cache when post meta is updated.
     */
    public function clearJobDataCacheOnMeta($meta_id, $object_id, $meta_key, $_meta_value): void
    {
        $this->invalidateJobDataCache($object_id);
    }

    /**
     * Clear job data cache when object terms are set.
     */
    public function clearJobDataCacheOnTax($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids): void
    {
        $this->invalidateJobDataCache($object_id);
    }

    /**
     * Clear job data cache when post status changes.
     */
    public function clearJobDataCacheOnStatusChange($new_status, $old_status, $post): void
    {
        if ($new_status !== $old_status && $post->post_type === 'lowongan') {
            $this->invalidateJobDataCache($post->ID);
        }
    }

    /**
     * Invalidate job data cache for a specific post if it's a 'lowongan' post type.
     *
     * @param int $post_id The post ID
     * @return bool True if cache was invalidated, false otherwise
     */
    private function invalidateJobDataCache(int $post_id): bool
    {
        $post_type = get_post_type($post_id);
        if ($post_type !== 'lowongan') {
            return false;
        }

        $jobDataCacheKey = JobDataFactory::FACTORY_JOB_PREFIX_CACHE . $post_id;
        $cardCacheKey = RESTData::CARD_CACHE_PREFIX . $post_id;
        $overlayCacheKeyLoggedIn = RESTData::OVERLAY_CACHE_PREFIX . $post_id . '_logged_in';
        $overlayCacheKeyPublic = RESTData::OVERLAY_CACHE_PREFIX . $post_id . '_public';
        $schemaCacheKey = JobSchemaOrg::SCHEMA_JOB_KEY_PREFIX . $post_id;

        // Use deleteMultiple for better performance - single network round trip
        $cacheKeys = [
            $jobDataCacheKey,
            $cardCacheKey,
            $overlayCacheKeyLoggedIn,
            $overlayCacheKeyPublic,
            $schemaCacheKey
        ];

        $deleteResults = Cache::deleteMultiple($cacheKeys);

        // Return true if any cache entry was deleted
        return !empty(array_filter($deleteResults));
    }
}