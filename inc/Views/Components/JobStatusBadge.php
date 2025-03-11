<?php
namespace AstraChild\Views\Components;

use AstraChild\Models\JobEntity;
use AstraChild\Controllers\JobController;
use AstraChild\Helpers\JobHelpers;

/**
 * Job Status Badge Component
 * 
 * Renders status badges for job listings
 */
class JobStatusBadge
{
    /**
     * Render status badge
     * 
     * @param JobEntity $job The job entity
     * @param array $options Display options
     * @return void
     */
    public function render(JobEntity $job, array $options = []): void
    {
        // Default options
        $default_options = [
            'show' => true,            // Whether to show any badge at all
            'show_if_not_urgent' => false, // Show badge even for non-urgent jobs
            
            // Specific status toggles - these take precedence over show_if_not_urgent
            'status_toggles' => [
                '0' => false,  // Normal status
                '1' => false,  // Reserved status (UNUSED)
                '2' => true,   // Urgent status
                '3' => true,   // Featured status
                '4' => true,   // Featured + Urgent status
            ],
            
            'position' => 'default',   // default, inline, absolute-top-left, etc.
            'size' => 'sm',           // sm, md, lg
            'classes' => '',          // Additional CSS classes
        ];
        
        $options = array_merge($default_options, $options);
        
        // Bail early if badge should not be shown at all
        if (!$options['show']) {
            return;
        }
        
        $status = $job->getAttribute('status');
        
        // Check if this specific status should be shown
        if (isset($options['status_toggles'][$status])) {
            // If explicitly defined in status_toggles, use that setting
            if (!$options['status_toggles'][$status]) {
                return; // This status is disabled
            }
        } else {
            // Fall back to the legacy urgent-only logic
            if (!$job->isUrgent() && !$options['show_if_not_urgent']) {
                return;
            }
        }
        
        $status_attrs = JobHelpers::getJobStatusAttributes($status);
        
        // Position classes
        $position_class = '';
        if ($options['position'] === 'absolute-top-left') {
            $position_class = 'absolute top-3 left-3';
        } elseif ($options['position'] === 'absolute-top-right') {
            $position_class = 'absolute top-3 right-3';
        } elseif ($options['position'] === 'inline') {
            $position_class = 'inline-flex';
        }
        
        // Size classes
        $size_class = 'text-sm';
        if ($options['size'] === 'sm') {
            $size_class = 'text-xs py-0.5 px-2';
        } elseif ($options['size'] === 'lg') {
            $size_class = 'text-base py-1.5 px-4';
        } else {
            $size_class = 'text-sm py-1 px-3';
        }
        
        // Base classes
        $base_class = 'flex items-center gap-1 rounded-full font-medium';
        
        ?>
        <div class="<?php echo esc_attr($position_class); ?>">
            <span class="<?php echo esc_attr("$base_class $size_class {$status_attrs['class']} {$options['classes']}"); ?>">
                <i class="<?php echo esc_attr($status_attrs['icon']); ?> mr-1"></i>
                <?php echo esc_html($status_attrs['label']); ?>
            </span>
        </div>
        <?php
    }
}