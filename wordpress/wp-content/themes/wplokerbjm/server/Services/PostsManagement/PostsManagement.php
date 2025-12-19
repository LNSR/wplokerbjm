<?php

namespace WPLokerBJM\Services\PostsManagement;

use WPLokerBJM\QueryBuilders\JobQuery;

/**
 * Handles job-related operations, including deletion and status updates.
 */
class PostsManagement
{
    public function deleteOldJobs(): void
    {
        try {
            // Fetch jobs older than 1 month but exclude ones with future deadline via JobQuery::oldJobsArgs
            $old_jobs = get_posts(JobQuery::oldJobsArgs());
            foreach ($old_jobs as $job) {
                $post_id = is_object($job) ? $job->ID : (int) $job;

                // Skip deletion if the job still has a deadline in the future (guard against accidental deletion)
                try {
                    $deadline = get_post_meta($post_id, 'deadline', true);
                    if (!empty($deadline)) {
                        $deadline_ts = strtotime($deadline . ' 23:59:59');
                        if ($deadline_ts !== false && $deadline_ts >= time()) {
                            error_log('PostsManagement::deleteOldJobs skipping deletion for post ' . $post_id . ' due to future deadline ' . $deadline);
                            continue;
                        }
                    }
                } catch (\Exception $e) {
                    // Do not block deletion if deadline cannot be read; log and continue
                    error_log('PostsManagement::deleteOldJobs error checking deadline for post ' . $post_id . ': ' . $e->getMessage());
                }

                try {
                    $attachments = get_posts(JobQuery::byParentArgs($post_id, true));
                    foreach ($attachments as $att_id) {
                        wp_delete_attachment($att_id, false);
                    }
                } catch (\Exception $e) {
                    error_log('PostsManagement::deleteOldJobs error deleting attachments for post ' . $post_id . ': ' . $e->getMessage());
                }

                try {
                    $comments = get_comments(['post_id' => $post_id, 'status' => 'all']);
                    foreach ($comments as $comment) {
                        wp_delete_comment($comment->comment_ID, false);
                    }
                } catch (\Exception $e) {
                    error_log('PostsManagement::deleteOldJobs error deleting comments for post ' . $post_id . ': ' . $e->getMessage());
                }

                try {
                    // Notify search engines to remove the URL from index
                    if (class_exists('RM_GIAPI')) {
                        $rm_giapi = new \RM_GIAPI();
                        $rm_giapi->send_to_api([get_permalink($post_id)], 'delete', false);
                    }
                } catch (\Exception $e) {
                    error_log('PostsManagement::deleteOldJobs error notifying API for post ' . $post_id . ': ' . $e->getMessage());
                }

                try {
                    wp_delete_post($post_id, false);
                } catch (\Exception $e) {
                    error_log('PostsManagement::deleteOldJobs error deleting post ' . $post_id . ': ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log('PostsManagement::deleteOldJobs error: ' . $e->getMessage());
        }
    }

    /**
     * Update job statuses based on deadlines:
     * - Set to normal (0) if past deadline
     * - Set to urgent (2) if within 7 days of deadline
     * - Leave unchanged otherwise
     * @return void
     */
    public function updateAllJobStatuses(): void
    {
        try {
            $job_items = get_posts(JobQuery::allJobsIdsArgs());
            foreach ($job_items as $job_item) {
                $post_id = is_object($job_item) ? (int) $job_item->ID : (int) $job_item;

                try {
                    $deadline = get_post_meta($post_id, 'deadline', true);
                    if (!$deadline) {
                        continue;
                    }

                    $status = (int) get_post_meta($post_id, 'status_pekerjaan', true);

                    PostsJobStatus::updateJobStatusIfExpired($post_id, $deadline, $status);
                    PostsJobStatus::setJobStatustoUrgent($post_id, $deadline, $status);
                } catch (\Exception $e) {
                    error_log('PostsManagement::updateAllJobStatuses error for post ' . $post_id . ': ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log('PostsManagement::updateAllJobStatuses error: ' . $e->getMessage());
        }
    }
}

/**
 * Helper class for updating job statuses based on deadlines.
 */
class PostsJobStatus
{
    /**
     * Set job status to expired(normal or not set) if the deadline has passed
     * @param int $post_id
     * @param string $deadline
     * @param int $current_status
     * @return void
     */
    public static function updateJobStatusIfExpired(int $post_id, string $deadline, int $current_status): void
    {
        try {
            $deadline_ts = strtotime($deadline . ' 23:59:59');
            if ($deadline_ts === false) {
                error_log('PostsManagement::updateJobStatusIfExpired invalid deadline for post ' . $post_id . ': ' . $deadline);
                return;
            }
            $now = time();
            if ($now > $deadline_ts && $current_status !== 0) {
                update_post_meta($post_id, 'status_pekerjaan', 0);
            }
        } catch (\Exception $e) {
            error_log('PostsManagement::updateJobStatusIfExpired error for post ' . $post_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Set job status to urgent if the deadline is within 7 days
     * @param int $post_id
     * @param string $deadline
     * @param int $current_status
     * @return void
     */
    public static function setJobStatustoUrgent(int $post_id, string $deadline, int $current_status): void
    {
        try {
            $deadline_ts = strtotime($deadline . ' 23:59:59');
            if ($deadline_ts === false) {
                error_log('PostsManagement::setJobStatustoUrgent invalid deadline for post ' . $post_id . ': ' . $deadline);
                return;
            }
            $now = time();
            $seven_days_ahead = strtotime('+7 days 23:59:59', strtotime('today', $now));
            if ($deadline_ts >= $now && $deadline_ts <= $seven_days_ahead && $current_status !== 2) {
                update_post_meta($post_id, 'status_pekerjaan', 2);
            }
        } catch (\Exception $e) {
            error_log('PostsManagement::setJobStatustoUrgent error for post ' . $post_id . ': ' . $e->getMessage());
        }
    }
}
