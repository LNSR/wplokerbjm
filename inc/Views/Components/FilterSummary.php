<?php
namespace AstraChild\Views\Components;

class FilterSummary
{
    /**
     * Render active filter summary
     * 
     * @param array $active_filters Array of active filters
     * @return void
     */
    public function render(array $active_filters): void
    {
        if (empty($active_filters)) {
            return;
        }
        
        ?>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php foreach ($active_filters as $key => $value): ?>
                <div class="flex items-center bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
                    <span class="font-medium"><?php echo esc_html($key); ?>:</span>
                    <span class="ml-1"><?php echo esc_html($value); ?></span>
                    <a href="<?php echo esc_url(remove_query_arg($key)); ?>" class="ml-2 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}