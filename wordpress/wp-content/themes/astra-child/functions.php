<?php

/**
 * Astra Child Theme Functions
 * This file reserved for plugins outside author who add custom functions to the child theme 
 * 
 * This file contains minimal theme functions and enqueues.
 * Most of the theme's functionality is handled by the dependency injection container
 * and services that are bootstrapped by the MU plugin.
 *
 * ⚠️  WARNING: Do not modify this file directly!
 * ⚠️  All custom functionality should be added through the DI container system.
 *
 * Bootstrap Process:
 * 1. WordPress loads MU plugins in alphabetical order
 * 2. astra-child-bootstrap.php loads first and initializes the DI container
 * 3. Container registers all services, hooks, and dependencies
 * 4. Theme functions.php loads last with minimal setup
 *
 * @see /wp-content/mu-plugins/astra-child-bootstrap.php - Main bootstrap file that initializes the DI container
 * @see /wp-content/themes/astra-child/inc/Core/Container.php - Dependency injection container
 * @see /wp-content/themes/astra-child/inc/Core/Init.php - Service initialization
 * @package Astra-Child
 * @since 1.0.0
 */

// Exit if accessed directly for security
if (!defined('ABSPATH')) {
    exit;
}

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