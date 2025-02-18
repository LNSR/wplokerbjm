<?php
namespace AstraChild\Views\Components;

/**
 * Hierarchical Filter Dropdown Component
 * 
 * Renders a dropdown with hierarchical options
 */
class HierarchicalFilterDropdown
{
    /**
     * Render hierarchical filter dropdown
     * 
     * @param string $name Filter name/parameter
     * @param string $label Label to display
     * @param array $terms Hierarchical terms array
     * @param string $selected_value Currently selected value
     * @return void
     */
    public function render(string $name, string $label, array $terms, string $selected_value = ''): void
    {
        ?>
        <div class="filter-group">
            <label for="<?php echo esc_attr($name); ?>" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo esc_html($label); ?>
            </label>
            <select 
                name="<?php echo esc_attr($name); ?>" 
                id="<?php echo esc_attr($name); ?>"
                class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
                <option value="">Semua <?php echo esc_html($label); ?></option>
                <?php $this->renderTermOptions($terms, $selected_value); ?>
            </select>
        </div>
        <?php
    }
    
    /**
     * Recursively render hierarchical term options
     * 
     * @param array $terms Hierarchical terms array
     * @param string $selected_value Currently selected value
     * @param int $depth Current depth level
     * @return void
     */
    private function renderTermOptions(array $terms, string $selected_value = '', int $depth = 0): void
    {
        foreach ($terms as $term_id => $term_data) {
            $term = $term_data['term'];
            $indentation = str_repeat('&nbsp;&nbsp;', $depth);
            $selected = ($term->slug === $selected_value) ? 'selected' : '';
            
            echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . 
                 $indentation . esc_html($term->name) . 
                 '</option>';
            
            // If this term has children, render them with increased depth
            if (!empty($term_data['children'])) {
                $this->renderTermOptions($term_data['children'], $selected_value, $depth + 1);
            }
        }
    }
}