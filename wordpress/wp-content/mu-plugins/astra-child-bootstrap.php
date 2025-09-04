<?php
/**
 * Astra Child Bootstrap MU Plugin
 *
 * Loads the Composer autoloader and initializes the DI container early in the WordPress lifecycle.
 */

// Exit if accessed directly for security
if (!defined('ABSPATH')) {
    exit;
}

// Register Composer autoloader
require_once dirname(__DIR__, 1) . '/themes/astra-child/vendor/autoload.php';

// Initialize the DI container
$astra_child_container = \AstraChild\Core\Container::getContainer();

// Bootstrap the theme hooks and services
$astra_child_init = $astra_child_container->get(\AstraChild\Core\Container\Init::class)->initialize();