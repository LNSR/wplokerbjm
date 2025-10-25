<?php

namespace WPLokerBJM\Services\Cron;

use WPLokerBJM\Contracts\HooksInterface;

/**
 * Centralizes cron scheduling and mapping of cron hooks to service callbacks.
 *
 * This keeps scheduling logic in a single place instead of scattering
 * `wp_schedule_event` calls across multiple services.
 */
class CronService implements HooksInterface
{
    public function __construct(
        private readonly \WPLokerBJM\Services\PostsManagement\PostsManagement $postsManagement,
        private readonly \WPLokerBJM\Services\Taxonomy\TaxonomyManagement $taxonomyManagement
    ) {
    }

    public function registerActions(): void
    {
        // Map cron hooks to service callbacks

        /** @see \WPLokerBJM\Services\PostsManagement\PostsManagement::deleteOldJobs() */
        add_action('wplokerbjm_delete_old_jobs', [$this->postsManagement, 'deleteOldJobs']);

        /** @see \WPLokerBJM\Services\PostsManagement\PostsManagement::updateAllJobStatuses() */
        add_action('wplokerbjm_update_job_statuses', [$this->postsManagement, 'updateAllJobStatuses']);

        /** @see \WPLokerBJM\Services\Taxonomy\TaxonomyManagement::deleteUnusedTerms() */
        add_action('wplokerbjm_cleanup_taxonomy', [$this->taxonomyManagement, 'deleteUnusedTerms']);

        // Ensure scheduled events exist (single place for scheduling)
        if (!wp_next_scheduled('wplokerbjm_delete_old_jobs')) {
            /** @see \WPLokerBJM\Services\PostsManagement\PostsManagement::deleteOldJobs() */
            wp_schedule_event(time(), 'daily', 'wplokerbjm_delete_old_jobs');
        }

        if (!wp_next_scheduled('wplokerbjm_update_job_statuses')) {
            /** @see \WPLokerBJM\Services\PostsManagement\PostsManagement::updateAllJobStatuses() */
            wp_schedule_event(time(), 'daily', 'wplokerbjm_update_job_statuses');
        }

        if (!wp_next_scheduled('wplokerbjm_cleanup_taxonomy')) {
            /** @see \WPLokerBJM\Services\Taxonomy\TaxonomyManagement::deleteUnusedTerms() */
            wp_schedule_event(time(), 'weekly', 'wplokerbjm_cleanup_taxonomy');
        }
    }

    public function registerFilters(): void
    {
        // No filters for cron service
    }
}
