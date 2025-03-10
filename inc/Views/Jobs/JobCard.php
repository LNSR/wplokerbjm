<?php
namespace AstraChild\Views\Jobs;

use AstraChild\Helpers\JobHelpers;
use AstraChild\Models\JobEntity;
use AstraChild\Controllers\JobController;
use AstraChild\Views\Components\JobStatusBadge;
use AstraChild\Views\Components\JobDeadlineBadge;

/**
 * Job Card View
 * 
 * Handles the rendering of job cards in listings
 */
class JobCard
{
    /**
     * @var JobStatusBadge
     */
    protected $statusBadge;
    
    /**
     * @var JobDeadlineBadge
     */
    protected $deadlineBadge;
    
    /**
     * Initialize the job card
     */
    public function __construct()
    {
        $this->statusBadge = new JobStatusBadge();
        $this->deadlineBadge = new JobDeadlineBadge();
    }

    /**
     * Render a job card
     * 
     * @param JobEntity|null $job_entity The job entity
     * @param array $options Display options
     * @return void
     */
    public function render(JobEntity $job_entity = null, array $options = []): void
    {
        $default_options = [
            'show_statuses' => [
                '0' => true,  // Normal (show by default)
                '2' => true,  // Urgent (show by default)
                '3' => true,  // Pinned (show by default)
                '4' => true   // Pinned & Urgent (show by default)
            ]
        ];
        
        $options = array_merge($default_options, $options);
        
        // Get job entity if not provided
        if ($job_entity === null) {
            $job_controller = new JobController();
            $job_entity = $job_controller->getJobEntity(get_the_ID());
        }
        
        // Skip rendering if this job's status should not be shown
        $status = $job_entity->getAttribute('status');
        if (!empty($status) && isset($options['show_statuses'][$status]) && !$options['show_statuses'][$status]) {
            return;
        }
        
        // Start output buffer
        ob_start();
        
        // Include template
        include get_stylesheet_directory() . '/template-parts/jobs/card-content.php';
        
        // Output content
        echo ob_get_clean();
    }
    
    /**
     * Render job status badge
     * 
     * @param JobEntity $job The job entity
     * @return void
     */
    public function renderStatusBadge(JobEntity $job): void
    {
        $this->statusBadge->render($job, [
            'status_toggles' => [
                '0' => false,  // Normal status
                '2' => true,   // Urgent status
                '3' => true,   // Featured status
                '4' => true    // Featured + Urgent status
            ],
            'show' => true,
            'position' => 'absolute-top-left',
            'size' => 'md'
        ]);
    }
    
    /**
     * Render deadline information
     * 
     * @param JobEntity $job The job entity
     * @return void
     */
    public function renderDeadline(JobEntity $job): void
    {
        $this->deadlineBadge->render($job, [
            'require_urgent' => true,  // Keep current behavior of only showing for urgent jobs
            'position' => 'absolute-top-right',
            'style' => 'badge',
            'show_date_if_expired' => true
        ]);
    }
    
    /**
     * Render job details section
     * 
     * @param JobEntity $job The job entity
     * @return void
     */
    public function renderJobDetails(JobEntity $job): void
    {
        ?>
        <div class="mb-0">
            <!-- Company name stays full width -->
            <p class="text-gray-600 font-bold mb-2">
                <i class="fas fa-building mr-2 text-blue-600"></i>
                <span class="font-bold"><?php echo esc_html($job->getAttribute('company')); ?></span>
            </p>
            <!-- Flex container for location, education and experience -->
            <div class="flex flex-wrap gap-x-4">
                <!-- Location -->
                <?php if (!JobHelpers::isReallyEmpty($job->getAttribute('location'))): ?>
                <p class="flex items-center text-gray-500">
                    <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                    <?php echo esc_html($job->getAttribute('location')); ?>
                </p>
                <?php endif; ?>

                <!-- Education when available -->
                <?php if (!JobHelpers::isReallyEmpty($job->getAttribute('education'))) : ?>
                    <p class="flex items-center text-gray-500">
                        <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>
                        <?php echo esc_html($job->getFormattedEducation()); ?>
                    </p>
                <?php endif; ?>

                <!-- Experience when available -->
                <?php if (!JobHelpers::isReallyEmpty($job->getAttribute('experience'))) : ?>
                    <p class="flex items-center text-gray-500">
                        <i class="fas fa-history mr-2 text-blue-600"></i>
                        <?php echo esc_html($job->getFormattedExperience()); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render relative time
     * 
     * @return void
     */
    public function renderRelativeTime(): void
    {
        $post_time = get_the_time('U');
        $current_time = current_time('timestamp');
        $time_diff = JobHelpers::translateTimeDiff(human_time_diff($post_time, $current_time));

        // Show relative time with absolute time as tooltip
        printf(
            '<span title="%s">%s yang lalu</span>',
            esc_attr(get_the_date('d M Y, H:i')),
            esc_html($time_diff)
        );
    }
}