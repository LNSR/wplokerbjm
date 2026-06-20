<?php

namespace WPLokerBJM\Core\Cron;
use WPLokerBJM\Core\Container\Attributes\Action;
/**
 * Cron hook key constants for centralized management.
 */
class WPCron
{
    const DELETE_OLD_JOBS = 'wplokerbjm_delete_old_jobs';
    const UPDATE_JOB_STATUSES = 'wplokerbjm_update_job_statuses';
    const CLEANUP_TAXONOMY = 'wplokerbjm_cleanup_taxonomy';

    /**
     * Registers cron hooks and ensures scheduled events exist.
     * to register it as an action hook on WordPress initialization.
     *
     * @return void
     */
    #[Action(hook: 'init')]
    public function registerCronWP(): void
    {

        $scheduleEvent = static function (string $hook, string $recurrence): void {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $recurrence, $hook);
            }
        };

        $scheduleEvent(self::DELETE_OLD_JOBS, 'daily');
        $scheduleEvent(self::UPDATE_JOB_STATUSES, 'daily');
        $scheduleEvent(self::CLEANUP_TAXONOMY, 'weekly');
    }
}