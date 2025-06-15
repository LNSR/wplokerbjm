<?php

namespace AstraChild\Services\PostsManagement;

use AstraChild\QueryBuilders\JobQuery;

/**
 * ? Subject to change: dedicated CronJob for better seperation of concerns.
 * 
 */
class PostsManagement
{
    public function register(): void
    {
        add_action('astra_child_delete_old_jobs', [$this, 'deleteOldJobs']);
        add_action('astra_child_update_job_statuses', [$this, 'updateAllJobStatuses']);

        if (! wp_next_scheduled('astra_child_delete_old_jobs')) {
            wp_schedule_event(time(), 'daily', 'astra_child_delete_old_jobs');
        }

        if (! wp_next_scheduled('astra_child_update_job_statuses')) {
            wp_schedule_event(time(), 'daily', 'astra_child_update_job_statuses');
        }
    }

    public function deleteOldJobs(): void
    {
        $old_jobs = get_posts(JobQuery::oldJobsArgs());
        foreach ($old_jobs as $job_id) {
            wp_delete_post($job_id, false);
        }
    }

    public function updateAllJobStatuses(): void
    {
        $job_ids = get_posts(JobQuery::allJobsIdsArgs());
        foreach ($job_ids as $post_id) {
            $deadline = get_post_meta($post_id, 'deadline', true);
            $status = (int) get_post_meta($post_id, 'status_pekerjaan', true);
            if ($deadline) {
                $this->updateJobStatusIfExpired($post_id, $deadline, $status);
            }
        }
    }

    /**
     * Summary of updateJobStatusIfExpired
     * @param int $post_id
     * @param string $deadline
     * @param int $current_status
     * @return void
     */
    public function updateJobStatusIfExpired(int $post_id, string $deadline, int $current_status): void
    {
        $deadline_ts = strtotime($deadline . ' 23:59:59');
        $now = time();
        // If the deadline has passed and status is not 0
        if ($now > $deadline_ts && $current_status !== 0) {
            update_post_meta($post_id, 'status_pekerjaan', 0);
        }
    }
}
