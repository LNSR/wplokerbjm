<?php

/**
 * Astra Child Theme Functions
 * 
 * @package Astra-Child
 * @since 1.0.0
 */

// Exit if accessed directly for security
if (!defined('ABSPATH')) {
    exit;
}

// Register Composer autoloader
require_once get_stylesheet_directory() . '/vendor/autoload.php';

use AstraChild\Core\Container;

// Initialize the DI container
$container = Container::getContainer();

// Bootstrap the theme
$init = $container->get(AstraChild\Core\Init::class);
$init->initialize();

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if (!function_exists('chld_thm_cfg_locale_css')):
    function chld_thm_cfg_locale_css($uri)
    {
        if (empty($uri) && is_rtl() && file_exists(get_template_directory() . '/rtl.css'))
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter('locale_stylesheet_uri', 'chld_thm_cfg_locale_css');

if (!function_exists('child_theme_configurator_css')):
    function child_theme_configurator_css()
    {
        wp_enqueue_style('chld_thm_cfg_child', trailingslashit(get_stylesheet_directory_uri()) . 'style.css', array('astra-theme-css'));
    }
endif;
add_action('wp_enqueue_scripts', 'child_theme_configurator_css', 10);

// END ENQUEUE PARENT ACTION
