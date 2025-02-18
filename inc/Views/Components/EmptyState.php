<?php
namespace AstraChild\Views\Components;

class EmptyState
{
    /**
     * Render an empty state message
     * 
     * @param string $message The message to display
     * @param string $icon Icon class (optional)
     * @param array $options Additional display options
     * @return void
     */
    public function render(string $message, string $icon = 'fa-search', array $options = []): void
    {
        $default_options = [
            'classes' => 'col-span-full text-center p-8 bg-gray-50 rounded-lg',
            'icon_classes' => 'text-gray-400 text-4xl mb-3',
            'message_classes' => 'text-gray-600'
        ];
        
        $options = array_merge($default_options, $options);
        
        ?>
        <div class="<?php echo esc_attr($options['classes']); ?>">
            <?php if ($icon): ?>
                <i class="fas <?php echo esc_attr($icon); ?> <?php echo esc_attr($options['icon_classes']); ?>"></i>
            <?php endif; ?>
            <p class="<?php echo esc_attr($options['message_classes']); ?>"><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
}