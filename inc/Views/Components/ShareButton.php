<?php
namespace AstraChild\Views\Components;

use AstraChild\Models\JobEntity;

/**
 * Share Button Component
 * 
 * Renders a share button for jobs
 */
class ShareButton 
{
    /**
     * Render share button
     * 
     * @param JobEntity $job The job entity
     * @param array $options Display options
     * @return void
     */
    public function render(JobEntity $job, array $options = []): void
    {
        $default_options = [
            'size' => 'md',       // sm, md, lg
            'position' => 'right', // left, center, right
            'show_text' => true,
            'show_icon' => true,
            'classes' => '',
        ];
        
        $options = array_merge($default_options, $options);
        
        // Generate size classes
        $size_class = 'text-sm py-2 px-4';
        if ($options['size'] === 'sm') {
            $size_class = 'text-xs py-1 px-3';
        } elseif ($options['size'] === 'lg') {
            $size_class = 'text-base py-3 px-5';
        }
        
        // Generate position classes
        $position_class = 'ml-auto';
        if ($options['position'] === 'left') {
            $position_class = 'mr-auto';
        } elseif ($options['position'] === 'center') {
            $position_class = 'mx-auto';
        }
        
        // Base button classes
        $button_class = 'share-button inline-flex items-center font-medium text-blue-600 bg-blue-50 rounded-lg ' . 
                        'hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors ' .
                        $size_class . ' ' . $position_class . ' ' . $options['classes'];
        
        ?>
        <button
            class="<?php echo esc_attr($button_class); ?>"
            data-post-id="<?php echo esc_attr($job->getAttribute('ID')); ?>"
        >
            <?php if ($options['show_icon']): ?>
                <i class="fas fa-share-alt <?php echo $options['show_text'] ? 'mr-2' : ''; ?>"></i>
            <?php endif; ?>
            
            <?php if ($options['show_text']): ?>
                <span>Bagikan</span>
            <?php endif; ?>
        </button>
        <?php
    }
}