<?php
namespace AstraChild\Views\Jobs;

use AstraChild\Helpers\JobHelpers;
use AstraChild\Models\JobEntity;
use AstraChild\Controllers\JobController;

/**
 * Job Card View
 * 
 * Handles the rendering of job cards in listings
 */
class JobCard
{
    /**
     * Render a job card
     * 
     * @param JobEntity|null $job_entity The job entity
     * @return void
     */
    public function render(JobEntity $job_entity = null): void
    {
        // Get job entity if not provided
        if ($job_entity === null) {
            $job_controller = new JobController();
            $job_entity = $job_controller->getJobEntity(get_the_ID());
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
        $status = $job->getAttribute('status');
        
        // Only show the status badge if it's urgent or pinned+urgent
        if ($job->isUrgent()) {
            $status_attrs = (new JobController())->getJobStatusAttributes($status);
            ?>
            <div class="absolute top-3 left-3">
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium <?php echo esc_attr($status_attrs['class']); ?>">
                    <i class="<?php echo esc_attr($status_attrs['icon']); ?> mr-1"></i>
                    <?php echo esc_html($status_attrs['label']); ?>
                </span>
            </div>
            <?php
        }
    }
    
    /**
     * Render deadline information
     * 
     * @param JobEntity $job The job entity
     * @return void
     */
    public function renderDeadline(JobEntity $job): void
    {
        $deadline = $job->getAttribute('deadline');
        
        if ($job->isUrgent() && !empty($deadline)) {
            // Get the deadline timestamp
            $deadline_timestamp = strtotime($deadline);
            $current_timestamp = current_time('timestamp');
            $time_diff = $deadline_timestamp - $current_timestamp;
            ?>
            <div class="absolute top-3 right-3">
                <div class="flex items-center bg-white bg-opacity-90 rounded-lg px-2 py-1 text-xs border <?php echo $time_diff > 0 ? 'border-green-200' : 'border-red-200'; ?> shadow-sm">
                    <i class="fas fa-clock <?php echo $time_diff > 0 ? 'text-green-600' : 'text-red-600'; ?> mr-1"></i>
                    <span class="font-medium">
                        <?php if ($time_diff > 0): ?>
                            <!-- Future deadline - show remaining time -->
                            <?php 
                                $human_diff = JobHelpers::translateTimeDiff(human_time_diff($current_timestamp, $deadline_timestamp)); 
                                echo "Deadline: $human_diff lagi";
                            ?>
                        <?php else: ?>
                            <!-- Past deadline - show expired notice -->
                            Berakhir: <?php echo date_i18n('d M Y', $deadline_timestamp); ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php
        }
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
                <?php echo esc_html($job->getAttribute('company')); ?>
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