<?php

namespace WPLokerBJM\Core\Cron;
use DI\Attribute\Injectable;
use WPLokerBJM\Core\Container\Attributes\Action;
/**
 * Cron hook key constants for centralized management.
 */
#[Injectable(lazy: true)]
class WPCron
{
    const DELETE_OLD_JOBS = 'wplokerbjm_delete_old_jobs';
    const UPDATE_JOB_STATUSES = 'wplokerbjm_update_job_statuses';
    const CLEANUP_TAXONOMY = 'wplokerbjm_cleanup_taxonomy';

    /**
     * @param self::DELETE_OLD_JOBS | self::UPDATE_JOB_STATUSES | self::CLEANUP_TAXONOMY $hook
     * @param 'hourly' | 'twicedaily' | 'daily' | 'weekly' | 'monthly' $recurrence
     */
    private function scheduleEvent(string $hook, string $recurrence): void {
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $recurrence, $hook);
        }
    }

    /**
     * Registers cron hooks and ensures scheduled events exist.
     * to register it as an action hook on WordPress initialization.
     *
     * @return void
     */
    #[Action('init')]
    public function registerCronWP(): void
    {
        $this->scheduleEvent(self::DELETE_OLD_JOBS, 'daily');
        $this->scheduleEvent(self::UPDATE_JOB_STATUSES, 'daily');
        $this->scheduleEvent(self::CLEANUP_TAXONOMY, 'weekly');
    }
}