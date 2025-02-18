<?php
function my_theme_enqueue_styles()
{
    wp_enqueue_style('astra-theme-css', get_template_directory_uri() . '/style.css', array(), ASTRA_THEME_VERSION, 'all');
    wp_enqueue_style('astra-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), wp_get_theme()->get('Version'), 'all');
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles', 15);
?>
