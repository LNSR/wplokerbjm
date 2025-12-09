<?php
/**
 * wplokerbjm Bootstrap MU Plugin
 *
 * Loads the Composer autoloader and initializes the DI container early in the WordPress lifecycle.
 * Runs before theme activation to ensure hooks are registered.
 */

// Exit if accessed directly for security
if (!defined('ABSPATH')) {
    exit;
}

// Only run if the active theme is 'wplokerbjm'
if (get_stylesheet() !== 'wplokerbjm') {
    return;
}

// Register Composer autoloader (loads theme's vendor dependencies)
require_once get_stylesheet_directory() . '/vendor/autoload.php';

try {
    // Initialize the DI container
    $wplokerbjm_container = \WPLokerBJM\Core\Container::getContainer();
    
    // Bootstrap the theme hooks via auto-discovered services
    $wplokerbjm_init = $wplokerbjm_container->get(\WPLokerBJM\Core\Container\Init::class);
    $wplokerbjm_init->initialize();
} catch (\Exception $e) {
    error_log('wplokerbjm Bootstrap error: ' . $e->getMessage());
    return;
}