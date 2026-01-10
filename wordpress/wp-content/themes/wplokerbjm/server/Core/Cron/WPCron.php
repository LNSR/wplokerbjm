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
    const REFRESH_BOT_DATA = 'wplokerbjm_refresh_bot_data';

    /**
     * Registers cron hooks and ensures scheduled events exist.
     * to register it as an action hook on WordPress initialization.
     *
     * @return void
     */
    #[Action(hook: 'init')]
    public static function registerCronWP(): void
    {

        $scheduleEvent = function (string $hook, string $recurrence): void {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $recurrence, $hook);
            }
        };

        // Cron hooks are now registered via attributes on their respective service methods
        // Scheduling logic remains here for centralized management

        // Ensure scheduled events exist (single place for scheduling)
        $scheduleEvent(WPCron::DELETE_OLD_JOBS, 'daily');
        $scheduleEvent(WPCron::UPDATE_JOB_STATUSES, 'daily');
        $scheduleEvent(WPCron::CLEANUP_TAXONOMY, 'weekly');
        $scheduleEvent(WPCron::REFRESH_BOT_DATA, 'hourly');
    }
}