<?php

namespace WPLokerBJM\Services\Cron;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Services\PostsManagement\PostsManagement;
use WPLokerBJM\Services\Taxonomy\TaxonomyManagement;

/**
 * Centralizes cron scheduling and mapping of cron hooks to service callbacks.
 *
 * This keeps scheduling logic in a single place instead of scattering
 * `wp_schedule_event` calls across multiple services.
 */
class CronService implements HooksInterface
{
    public function registerActions(): void
    {
        add_action('wplokerbjm_delete_old_jobs', [PostsManagement::class, 'deleteOldJobs']);
        add_action('wplokerbjm_update_job_statuses', [PostsManagement::class, 'updateAllJobStatuses']);
        add_action('wplokerbjm_cleanup_taxonomy', [TaxonomyManagement::class, 'deleteUnusedTerms']);

        // Ensure scheduled events exist (single place for scheduling)
        if (!wp_next_scheduled('wplokerbjm_delete_old_jobs')) {
            wp_schedule_event(time(), 'daily', 'wplokerbjm_delete_old_jobs');
        }

        if (!wp_next_scheduled('wplokerbjm_update_job_statuses')) {
            wp_schedule_event(time(), 'daily', 'wplokerbjm_update_job_statuses');
        }

        if (!wp_next_scheduled('wplokerbjm_cleanup_taxonomy')) {
            wp_schedule_event(time(), 'weekly', 'wplokerbjm_cleanup_taxonomy');
        }
    }

    public function registerFilters(): void
    {
        // No filters for cron service
    }
}
