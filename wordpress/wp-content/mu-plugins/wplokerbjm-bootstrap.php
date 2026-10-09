<?php

declare(strict_types=1);

namespace WPLokerBJM;

use Nette\Loaders\RobotLoader;
use WPLokerBJM\Core\Wordpress\Theme\ThemeHooks;
use WPLokerBJM\Core\Container\Init;
use WPLokerBJM\Core\Container\WPLokerBJMContainer;

// Exit if accessed directly for security
!defined('ABSPATH') && exit;

/**
 * @package MU-Plugin
 * Plugin name: WPLokerBJM Infrastructure
 * Description: Bootstraps the wplokerbjm theme — sets up RobotLoader class autoloading
 * and initializes the PHP-DI container early in the WordPress lifecycle.
 * Author: MaulanaSR
 * RequiresPHP: 8.5
 * @see ThemeHooks
 */
(static function () {
    $themeName = 'wplokerbjm';
    if (get_stylesheet() !== $themeName) return;
    $themeRoot = WP_CONTENT_DIR . '/themes/' . $themeName;
    require_once $themeRoot . '/vendor/autoload.php';

    try {
        $robotLoader = new RobotLoader()
            ->addDirectory($themeRoot . '/server/')
            ->setCacheDirectory($themeRoot . '/cache/robotloader/')
            ->setAutoRefresh(defined('WP_ENV') && WP_ENV === 'development')
            ->reportParseErrors(defined('WP_DEBUG') && WP_DEBUG)
            ->register();

        new WPLokerBJMContainer(robotLoader: $robotLoader, cacheDir: \sprintf('%s/cache', $themeRoot))
            ->initContainerBuilder()
            ->setExtraRuntimeDefinitionsSet([RobotLoader::class, $robotLoader])
            ->buildContainer()
            ->get(Init::class)
            ->initialize();
    } catch (\Throwable $e) {
        error_log(
            sprintf(
                'wplokerbjm Bootstrap error: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            )
        );
    }
})();
