<?php
namespace AstraChild\Views\Components;

class LoadMore
{
    /**
     * Render load more button component
     * 
     * @param array $page_data Pagination data
     * @param string $target_element Target element ID prefix
     * @param string|null $callback_name JavaScript callback function name (optional)
     * @return void
     */
    public function render(array $page_data, string $target_element = '', string $callback_name = null): void
    {
        $max_pages = $page_data['max_pages'] ?? 1;
        $current_page = $page_data['current_page'] ?? 1;
        
        // Only show button if we have more pages
        if ($current_page >= $max_pages) {
            return;
        }
        ?>
        <div class="mt-8 flex justify-center" id="<?php echo esc_attr($target_element . '-loadmore-container'); ?>">
            <button id="<?php echo esc_attr($target_element); ?>-load-more" 
                    type="button"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition duration-200"
                    data-page="<?php echo esc_attr($current_page); ?>"
                    data-max-pages="<?php echo esc_attr($max_pages); ?>"
                    data-target="<?php echo esc_attr($target_element); ?>"
                    <?php if ($callback_name): ?>onclick="<?php echo esc_js($callback_name); ?>()"<?php endif; ?>>
                Lihat Lowongan Lainnya
            </button>
        </div>
        <?php
    }
}