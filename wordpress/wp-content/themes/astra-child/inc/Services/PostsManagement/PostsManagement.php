<?php

namespace AstraChild\Services\PostsManagement;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\QueryBuilders\AttachmentQuery;

/**
 * Handles job-related operations, including deletion and status updates.
 */
class PostsManagement
{

    public function deleteOldJobs(): void
    {
        $old_jobs = get_posts(JobQuery::oldJobsArgs());
        foreach ($old_jobs as $job) {
            $post_id = is_object($job) ? $job->ID : (int) $job;

            $attachments = get_posts(AttachmentQuery::byParentArgs($post_id, true));
            foreach ($attachments as $att_id) {
                wp_delete_attachment($att_id, false);
            }

            $comments = get_comments(['post_id' => $post_id, 'status' => 'all']);
            foreach ($comments as $comment) {
                wp_delete_comment($comment->comment_ID, false);
            }

            wp_delete_post($post_id, false);
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
        if ($now > $deadline_ts && $current_status !== 0) {
            update_post_meta($post_id, 'status_pekerjaan', 0);
        }
    }
}
