<?php
namespace AstraChild\Services\PostsManagement;

use AstraChild\Contracts\HooksInterface;
use AstraChild\Core\Cache;

/**
 * Listens to post changes and clears relevant transients.
 */
class PostsListener implements HooksInterface
{
    public function registerActions(): void
    {
        add_action('save_post', [$this, 'onPostChange'], 20, 3);
        add_action('delete_post', [$this, 'onPostChange'], 20, 1);
        add_action('wp_trash_post', [$this, 'onPostChange'], 20, 1);
        add_action('untrash_post', [$this, 'onPostChange'], 20, 1);
    }

    public function registerFilters(): void
    {
        // Register filters here
    }

    /**
     * Handle post changes to clear transients.
     *
     * @param int $post_id Post ID.
     * @param \WP_Post $post Post object.
     * @param bool $update Whether this is an update.
     */
    public function onPostChange($post_id, ?\WP_Post $post = null, ?bool $update = null): void
    {
        // API-related caches
        Cache::deletePattern('auto_suggestion_%');
        Cache::deletePattern('dynamic_search_%');
        Cache::deletePattern('load_more_%');
        Cache::deletePattern('single_overlay_%');
        Cache::deletePattern('query_card_%');
        Cache::deletePattern('carousel_jobs_api_%');


        $post_ids_clear = [$post_id];
        $frontpage_id = get_option('page_on_front') ? (int) get_option('page_on_front') : 0;

        if ($frontpage_id && $frontpage_id !== $post_id) {
            $post_ids_clear[] = $frontpage_id;
        }
        foreach ($post_ids_clear as $clear_id) {
            /** MetaBoxData Job cache related */
            Cache::delete('custom_fields_job_data_' . $clear_id);
            Cache::delete('taxonomies_job_data_' . $clear_id);

            Cache::delete('job_schema_' . $clear_id);
            Cache::delete('card_data_' . $clear_id);
            Cache::delete('single_overlay_data_' . $clear_id);
            Cache::delete('single_view_props_' . $clear_id);
            Cache::delete('job_data_factory_' . $clear_id);
        }
    }
}