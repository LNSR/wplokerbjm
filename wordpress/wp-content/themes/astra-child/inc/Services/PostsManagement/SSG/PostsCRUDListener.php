<?php

namespace AstraChild\Services\PostsManagement\SSG;

use AstraChild\Contracts\HooksInterface;
use AstraChild\Services\Utilities\SSG\LiteSpeedIntegration;

/**
 * Listens to post CRUD events and notifies the SSG trigger service.
 */
class PostsCRUDListener implements HooksInterface
{
    public function __construct(
        private \AstraChild\Services\PostsManagement\SSG\TriggerBuild $triggerBuild,
        private \AstraChild\Services\Utilities\SSG\SSGUtilities $ssgUtilities
    )
    {
    }

    public function registerActions(): void
    {
        // Use priority 10; adjust as needed (LiteSpeed typically uses 10-20)
        add_action('save_post', [$this, 'onSavePost'], 10, 3);
        
        // Hook into post deletion with high priority to run before other cleanup
        add_action('before_delete_post', [$this, 'onBeforeDeletePost'], 1, 1);
        add_action('wp_trash_post', [$this, 'onTrashPost'], 1, 1);
        
        // Hook into LiteSpeed cache purge events to coordinate
        if (LiteSpeedIntegration::isActive()) {
            $priorities = LiteSpeedIntegration::getHookPriorities();
            add_action('litespeed_purge_post', [$this, 'onLiteSpeedPurgePost'], $priorities['ssg_after_litespeed'], 1);
            add_action('litespeed_purge_all', [$this, 'onLiteSpeedPurgeAll'], $priorities['ssg_after_litespeed']);
        }

        // Register scheduled event handler
        add_action('ssg_delayed_post_update', [$this, 'handleDelayedPostUpdate'], 10, 1);
    }

    public function registerFilters(): void
    {
        // No filters
    }

    public function onSavePost(int $post_id, \WP_Post $post, bool $update): void
    {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Skip if this is a LiteSpeed cache operation
        if (LiteSpeedIntegration::isCacheOperation()) {
            LiteSpeedIntegration::logCoordination("Skipping SSG trigger for LiteSpeed cache operation", ['post_id' => $post_id]);
            return;
        }

        // Skip during LiteSpeed maintenance operations
        if (LiteSpeedIntegration::shouldSkipDuringMaintenance()) {
            LiteSpeedIntegration::logCoordination("Skipping SSG trigger during LiteSpeed maintenance", ['post_id' => $post_id]);
            return;
        }

        // Optionally skip non-public posts
        if (!in_array($post->post_status, ['publish', 'private', 'future'], true)) {
            // If you want to only handle published posts, require 'publish' here
            return;
        }

        // Check if we recently triggered a build for this post to prevent rapid successive triggers
        $debounceKey = "ssg_post_debounce_{$post_id}";
        $lastTrigger = get_transient($debounceKey);

        if ($lastTrigger !== false) {
            error_log("SSG PostsCrudListener: Skipping duplicate trigger for post {$post_id} within debounce window");
            return;
        }

        $permalink = get_permalink($post_id);
        $home = home_url('/');

        $paths = $this->ssgUtilities->collectPathsFromPost($post_id, $permalink, $home);

        // Set debounce transient before triggering
        $debounceTiming = LiteSpeedIntegration::getDebounceTiming();
        $debounceSeconds = LiteSpeedIntegration::isActive() ? $debounceTiming['litespeed_coordination'] : $debounceTiming['normal_operation'];
        set_transient($debounceKey, time(), $debounceSeconds);

        // Trigger GitHub Actions for updates/creations
        $this->triggerBuild->trigger($paths, $update ? 'post_updated' : 'post_created');
    }

    // Note: Delete operations are handled locally within this service
    // No need for onDeletedPost method - deletions happen immediately

    /**
     * Handle post deletion before it's actually deleted
     */
    public function onBeforeDeletePost(int $post_id): void
    {
        $this->ssgUtilities->deleteSSGFile($post_id, 'post_deleted');
    }

    /**
     * Handle post trashing
     */
    public function onTrashPost(int $post_id): void
    {
        $this->ssgUtilities->deleteSSGFile($post_id, 'post_trashed');
    }

    /**
     * Check if LiteSpeed Cache plugin is active
     */
    private function isLiteSpeedCacheActive(): bool
    {
        return LiteSpeedIntegration::isActive();
    }

    /**
     * Check if current operation is triggered by LiteSpeed Cache
     */
    private function isLiteSpeedCacheOperation(): bool
    {
        return LiteSpeedIntegration::isCacheOperation();
    }

    /**
     * Handle LiteSpeed post purge events
     */
    public function onLiteSpeedPurgePost(int $post_id): void
    {
        LiteSpeedIntegration::logCoordination("LiteSpeed purged post, coordinating SSG update", ['post_id' => $post_id]);
        
        // Add a small delay to ensure LiteSpeed operations complete first
        wp_schedule_single_event(time() + 2, 'ssg_delayed_post_update', [$post_id]);
    }

    /**
     * Handle LiteSpeed full cache purge
     */
    public function onLiteSpeedPurgeAll(): void
    {
        LiteSpeedIntegration::logCoordination("LiteSpeed purged all cache, triggering full SSG rebuild");
        
        // Trigger full site rebuild
        $this->triggerBuild->trigger([home_url('/')], 'litespeed_full_purge');
    }
}