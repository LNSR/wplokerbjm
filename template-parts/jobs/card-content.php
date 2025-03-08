<?php
/**
 * Template part for displaying job card content
 * 
 * @var JobEntity $job_entity Job entity from the view
 * @var JobCard $this The view instance
 */

use AstraChild\Controllers\JobController;
use AstraChild\Models\JobEntity;

// If no job entity is passed, get one
if (empty($job_entity)) {
    $job_controller = new JobController();
    $job_entity = $job_controller->getJobEntity(get_the_ID());
}

// If we still don't have a job entity, exit early
if (!$job_entity) {
    return;
}
?>

<div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 p-6 animate-fade-in relative">
    <!-- Job Status Badge -->
    <?php $this->renderStatusBadge($job_entity); ?>
    
    <!-- Deadline information -->
    <?php $this->renderDeadline($job_entity); ?>
    
    <!-- Add an invisible spacer when badge is present -->
    <?php if ($job_entity->isUrgent()): ?>
        <div class="h-6"></div>
    <?php endif; ?>

    <!-- Title and date section -->
    <div class="flex justify-between items-start gap-4 mb-4">
        <div class="flex items-center gap-3">
            <h3 class="text-xl font-semibold text-gray-900 hover:text-blue-600 transition-colors">
                <a href="<?php echo esc_url($job_entity->getAttribute('permalink')); ?>">
                    <?php echo esc_html($job_entity->getAttribute('title')); ?>
                </a>
            </h3>
        </div>
        
        <span class="text-sm text-gray-500 whitespace-nowrap">
            <?php $this->renderRelativeTime(); ?>
        </span>
    </div>

    <!-- Job details section -->
    <?php $this->renderJobDetails($job_entity); ?>

    <div class="flex items-center justify-end border-t border-gray-100">
        <a href="<?php the_permalink(); ?>"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">
            Lihat Detail
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</div>