<?php
namespace AstraChild\Views\Components;

class FilterDropdown
{
    /**
     * Render a filter dropdown
     * 
     * @param string $name Field name
     * @param string $label Label text
     * @param array $options Dropdown options
     * @param string $selected Currently selected value
     * @param string $default Default option text
     * @return void
     */
    public function render(string $name, string $label, array $options, string $selected = '', string $default = 'Semua'): void
    {
        $id = 'filter-' . $name;
        ?>
        <div class="w-full">
            <label for="<?php echo esc_attr($id); ?>" class="block text-sm font-medium text-gray-700 mb-1">
                <?php echo esc_html($label); ?>
            </label>
            <select id="<?php echo esc_attr($id); ?>" 
                    name="<?php echo esc_attr($name); ?>" 
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-base">
                <option value=""><?php echo esc_html($default); ?></option>
                <?php foreach ($options as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($selected, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }
}