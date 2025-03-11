<?php
namespace AstraChild\Views\Components;

class Pagination
{
    /**
     * Render pagination component
     * 
     * @param array $pagination_data Pagination information
     * @param string $target_element ID of element to update via AJAX
     * @param string $callback_name JS callback function name
     * @return void 
     */
    public function render(array $pagination_data, string $target_element = '', string $callback_name = ''): void
    {
        $max_pages = $pagination_data['max_pages'] ?? 1;
        $current_page = $pagination_data['current_page'] ?? 1;
        
        if ($max_pages <= 1) return;
        
        echo '<div class="mt-8 flex justify-center gap-2" id="' . esc_attr($target_element . '-pagination') . '">';
        
        for ($i = 1; $i <= $max_pages; $i++) {
            $is_current = $i === (int)$current_page;
            echo '<button type="button"
                data-page="' . esc_attr($i) . '"
                data-target="' . esc_attr($target_element) . '"';
            
            if (!empty($callback_name)) {
                echo ' onclick="' . esc_attr($callback_name) . '(' . esc_attr($i) . ')"';
            }
            
            echo ' class="page-number px-4 py-2 rounded-lg ' . 
                ($is_current ? 'bg-blue-600 text-white' : 'bg-white text-blue-600 hover:bg-blue-50') . 
                ' border border-blue-200 transition-colors">' . 
                esc_html($i) . 
                '</button>';
        }
        
        echo '</div>';
    }
}