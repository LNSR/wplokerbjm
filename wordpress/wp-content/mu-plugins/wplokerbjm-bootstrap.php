<?php
/**
 * wplokerbjm Bootstrap MU Plugin
 *
 * Loads the Composer autoloader and initializes the DI container early in the WordPress lifecycle.
 */

// Exit if accessed directly for security
if (!defined('ABSPATH')) {
    exit;
}

// Only run if the active theme is 'wplokerbjm'
if (get_stylesheet() !== 'wplokerbjm') {
    return;
}

// Register Composer autoloader
require_once get_stylesheet_directory() . '/vendor/autoload.php';

// Initialize the DI container
$wplokerbjm_container = \WPLokerBJM\Core\Container::getContainer();
// Bootstrap the theme hooks and services
$wplokerbjm_init = $wplokerbjm_container->get(\WPLokerBJM\Core\Container\Init::class)->initialize();