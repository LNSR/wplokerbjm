<?php
declare(strict_types=1);
use WPLokerBJM\Core\Container\Init;
use WPLokerBJM\Core\Container\WPLokerBJMContainer;
/**
 * wplokerbjm Bootstrap MU Plugin
 *
 * Loads the Composer autoloader and initializes the DI container early in the WordPress lifecycle.
 * Runs before theme activation to ensure hooks are registered.
 */
// Exit if accessed directly for security   
!defined('ABSPATH') && exit;

/**
 * Some notes on the bootstrap process:
 * - This MU-Plugin is reliant on some theme functionalities from WP hence it should be activated with theme enabled.
 * @see WPLokerBJM\Core\Theme\ThemeInject
 */
(static function () {
    $theme = 'wplokerbjm';
    if (get_stylesheet() !== $theme)
        return;

    require_once WP_CONTENT_DIR . '/themes/' . $theme . '/vendor/autoload.php';

    try {
        /** @var Init $init */
        $init = WPLokerBJMContainer::getContainer()->get(Init::class);
        $init->initialize();
    } catch (\Exception $e) {
        error_log('wplokerbjm Bootstrap error: ' . $e->getMessage());
    }
})();