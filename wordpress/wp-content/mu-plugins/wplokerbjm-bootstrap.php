<?php
declare(strict_types=1);
/**
 * wplokerbjm Bootstrap MU Plugin
 *
 * Loads the Composer autoloader and initializes the DI container early in the WordPress lifecycle.
 * Runs before theme activation to ensure hooks are registered.
 */
use WPLokerBJM\Core\Container\Init;
// Exit if accessed directly for security
!defined('ABSPATH') && exit;

(static function () {
    // Only run if the active theme is 'wplokerbjm'
    if (get_stylesheet() !== 'wplokerbjm') return;

    require_once get_stylesheet_directory() . '/vendor/autoload.php';

    try {
        $container = \WPLokerBJM\Core\Container\Container::getContainer();

        /** @var Init $init */
        $init = $container->get(Init::class);
        $init->initialize();
    } catch (\Exception $e) {
        error_log('wplokerbjm Bootstrap error: ' . $e->getMessage());
    }
})();