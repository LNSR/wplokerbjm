<?php

namespace AstraChild\Services\Cron;

use AstraChild\Contracts\HooksInterface;

/**
 * Centralizes cron scheduling and mapping of cron hooks to service callbacks.
 *
 * This keeps scheduling logic in a single place instead of scattering
 * `wp_schedule_event` calls across multiple services.
 */
class CronService implements HooksInterface
{
    public function __construct(
        private readonly \AstraChild\Services\PostsManagement\PostsManagement $postsManagement,
        private readonly \AstraChild\Services\Taxonomy\TaxonomyManagement $taxonomyManagement
    ) {
    }

    public function registerActions(): void
    {
        // Map cron hooks to service callbacks

        /** @see \AstraChild\Services\PostsManagement\PostsManagement::deleteOldJobs() */
        add_action('astra_child_delete_old_jobs', [$this->postsManagement, 'deleteOldJobs']);

        /** @see \AstraChild\Services\PostsManagement\PostsManagement::updateAllJobStatuses() */
        add_action('astra_child_update_job_statuses', [$this->postsManagement, 'updateAllJobStatuses']);

        /** @see \AstraChild\Services\Taxonomy\TaxonomyManagement::deleteUnusedTermsCron() */
        add_action('astra_child_cleanup_taxonomy', [$this->taxonomyManagement, 'deleteUnusedTermsCron']);

        // Ensure scheduled events exist (single place for scheduling)
        if (!wp_next_scheduled('astra_child_delete_old_jobs')) {
            wp_schedule_event(time(), 'daily', 'astra_child_delete_old_jobs');
        }

        if (!wp_next_scheduled('astra_child_update_job_statuses')) {
            wp_schedule_event(time(), 'daily', 'astra_child_update_job_statuses');
        }

        if (!wp_next_scheduled('astra_child_cleanup_taxonomy')) {
            wp_schedule_event(time(), 'weekly', 'astra_child_cleanup_taxonomy');
        }
    }

    public function registerFilters(): void
    {
        // No filters for cron service
    }
}
