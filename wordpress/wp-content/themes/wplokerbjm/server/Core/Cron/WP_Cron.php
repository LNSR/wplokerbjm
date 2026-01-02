<?php

namespace WPLokerBJM\Core\Cron;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Core\Posts\PostsManagement;
use WPLokerBJM\Core\Taxonomy\TaxonomyManagement;
use WPLokerBJM\Services\Utilities\SSG\BotDetection;

/**
 * Centralizes cron scheduling and mapping of cron hooks to service callbacks.
 *
 * This keeps scheduling logic in a single place instead of scattering
 * `wp_schedule_event` calls across multiple services.
 */
class WP_Cron implements HooksInterface
{
    public function __construct(
        private readonly BotDetection $botDetection,
        private readonly PostsManagement $postsManagement,
        private readonly TaxonomyManagement $taxonomyManagement
    ) {
    }

    public function registerActions(): void
    {
        add_action('wplokerbjm_delete_old_jobs', fn() => $this->postsManagement->deleteOldJobs());
        add_action('wplokerbjm_update_job_statuses', fn() => $this->postsManagement->updateAllJobStatuses());
        add_action('wplokerbjm_cleanup_taxonomy', fn() => $this->taxonomyManagement->deleteUnusedTerms());
        add_action('wplokerbjm_refresh_bot_data', fn() => $this->botDetection->refreshBotData());

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

        if (!wp_next_scheduled('wplokerbjm_refresh_bot_data')) {
            wp_schedule_event(time(), 'hourly', 'wplokerbjm_refresh_bot_data');
        }
    }

    public function registerFilters(): void
    {
        // No filters for cron service
    }
}
