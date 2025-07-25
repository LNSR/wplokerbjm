<?php
namespace AstraChild\Layouts;

class Layouts
{
    public function render($layout)
    {
        return match ($layout) {
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
        };
    }

    public function getHeader()
    {
        // WP function to get navigation menu
        $nav = wp_nav_menu([
            'theme_location' => 'primary',
            'echo' => false,
        ]);
        $logo = function_exists('get_custom_logo') ? get_custom_logo() : '';

        ob_start();
        ?>
        <header class="relative !pb-4 border-b-2 border-[var(--ast-global-color-7)] min-h-[86px]" id="header"
            data-props='<?= esc_attr(json_encode(['logo' => $logo])); ?>'>
        </header>
        <?php
        return ob_get_clean();
    }

    public function getFooter()
    {
        ob_start();
        ?>
        <footer class="min-h-[50px] pt-2 border-t-2 border-[var(--ast-global-color-7)]" id="footer">
        </footer>
        <?php
        return ob_get_clean();
    }

}