<?php
namespace AstraChild\Views\Components;

use AstraChild\Models\JobEntity;
use AstraChild\Helpers\JobHelpers;

/**
 * Job Deadline Badge Component
 * 
 * Renders deadline information for jobs
 */
class JobDeadlineBadge
{
    /**
     * Render deadline badge
     * 
     * @param JobEntity $job The job entity
     * @param array $options Display options
     * @return void
     */
    public function render(JobEntity $job, array $options = []): void
    {
        // Default options
        $default_options = [
            'show' => true,                    // Whether to show the badge at all
            'require_urgent' => false,         // Only show for urgent jobs
            
            // Granular toggles for specific deadline states
            'deadline_toggles' => [
                'future' => true,              // Show future deadlines (not expired)
                'expired' => true,             // Show expired deadlines
                'urgent' => true,              // Show urgent deadlines (< 3 days left)
                'recent' => true,              // Show recently expired (< 7 days ago)
                'distant_future' => true,      // Show deadlines far in the future (> 14 days)
                'long_expired' => false,       // Show long expired deadlines (> 14 days ago)
            ],
            
            'urgent_threshold' => 3,           // Days left to consider a deadline urgent
            'distant_future_threshold' => 14,  // Days beyond which a deadline is "distant future" 
            'long_expired_threshold' => 14,    // Days beyond which an expired deadline is "long expired"
            
            'position' => 'default',           // default, absolute-top-right, etc.
            'style' => 'badge',                // badge, inline, compact
            'show_date_if_expired' => true,    // Show full date for expired deadlines
            'classes' => '',                   // Additional CSS classes
        ];
        
        $options = array_merge($default_options, $options);
        
        // Bail early if badge should not be shown at all
        if (!$options['show']) {
            return;
        }
        
        // Check deadline attribute
        $deadline = $job->getAttribute('deadline');
        if (empty($deadline)) {
            return;
        }
        
        // Process deadline info
        $deadline_timestamp = strtotime($deadline);
        $current_timestamp = current_time('timestamp');
        $time_diff = $deadline_timestamp - $current_timestamp;
        $days_diff = ceil($time_diff / (60 * 60 * 24));
        $is_future = $days_diff > 0;
        
        // Determine deadline state
        $is_urgent = $is_future && $days_diff <= $options['urgent_threshold'];
        $is_distant_future = $is_future && $days_diff > $options['distant_future_threshold'];
        $is_recent_expired = !$is_future && abs($days_diff) <= $options['long_expired_threshold'];
        $is_long_expired = !$is_future && abs($days_diff) > $options['long_expired_threshold'];
        
        // Check if this specific deadline state should be displayed
        $show_deadline = true;
        
        // If requiring urgent status and job is not urgent, bail
        if ($options['require_urgent'] && !$job->isUrgent()) {
            return;
        }
        
        // Apply granular deadline toggles
        if ($is_future) {
            if ($is_urgent) {
                $show_deadline = $options['deadline_toggles']['urgent'];
            } elseif ($is_distant_future) {
                $show_deadline = $options['deadline_toggles']['distant_future'];
            } else {
                $show_deadline = $options['deadline_toggles']['future'];
            }
        } else {
            if ($is_recent_expired) {
                $show_deadline = $options['deadline_toggles']['recent'];
            } elseif ($is_long_expired) {
                $show_deadline = $options['deadline_toggles']['long_expired'];
            } else {
                $show_deadline = $options['deadline_toggles']['expired'];
            }
        }
        
        // Bail if this deadline state should not be shown
        if (!$show_deadline) {
            return;
        }
        
        // Position classes remain unchanged
        $position_class = '';
        if ($options['position'] === 'absolute-top-right') {
            $position_class = 'absolute top-3 right-3';
        } elseif ($options['position'] === 'absolute-top-left') {
            $position_class = 'absolute top-3 left-3';
        }
        
        // Style classes with additional state-specific styling
        if ($options['style'] === 'badge') {
            $style_class = 'bg-white bg-opacity-90 rounded-lg px-2 py-1 text-xs border shadow-sm ';
            
            // Add state-specific colors
            if ($is_future) {
                if ($is_urgent) {
                    $style_class .= 'border-yellow-300'; // Urgent deadlines
                } else {
                    $style_class .= 'border-green-200';  // Future deadlines
                }
            } else {
                $style_class .= 'border-red-200';        // Expired deadlines
            }
        } elseif ($options['style'] === 'compact') {
            $style_class = 'text-xs';
        } else { // inline
            $style_class = 'text-xs rounded-full px-2 py-1 ';
            
            // Add state-specific colors
            if ($is_future) {
                if ($is_urgent) {
                    $style_class .= 'bg-yellow-100'; // Urgent deadlines
                } else {
                    $style_class .= 'bg-green-100';  // Future deadlines
                }
            } else {
                $style_class .= 'bg-red-100';        // Expired deadlines
            }
        }
        
        ?>
        <div class="<?php echo esc_attr($position_class); ?>">
            <div class="flex items-center <?php echo esc_attr($style_class . ' ' . $options['classes']); ?>">
                <i class="fas fa-clock <?php 
                if ($is_future) {
                    echo $is_urgent ? 'text-yellow-600' : 'text-green-600';
                } else {
                    echo 'text-red-600';
                }
                ?> mr-1"></i>
                <span class="font-medium">
                    <?php if ($is_future): ?>
                        <?php
                        if ($options['style'] === 'compact') {
                            // Just show days
                            echo $days_diff . ' hari lagi';
                        } else {
                            // Show nicely formatted time
                            $human_diff = JobHelpers::translateTimeDiff(
                                human_time_diff($current_timestamp, $deadline_timestamp)
                            );
                            echo "Deadline: $human_diff lagi";
                        }
                        ?>
                    <?php else: ?>
                        <?php if ($options['show_date_if_expired']): ?>
                            Berakhir: <?php echo date_i18n('d M Y', $deadline_timestamp); ?>
                        <?php else: ?>
                            <?php echo 'Berakhir ' . abs($days_diff) . ' hari lalu'; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php
    }
}