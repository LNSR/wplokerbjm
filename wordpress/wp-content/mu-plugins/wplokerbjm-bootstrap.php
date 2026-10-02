<?php

declare(strict_types=1);

namespace WPLokerBJM;

use Nette\Loaders\RobotLoader;
use WPLokerBJM\Core\Wordpress\Theme\ThemeProp;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyAutowireScanner;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksScanner;
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
 * @see ThemeProp
 */
class Bootstrap
{
    public private(set) static ?string $themeRoot = null;

    /**
     * Entry point. Called once from this file after the class definition.
     */
    public static function boot(string $themeName): void
    {
        if (get_stylesheet() !== $themeName) {
            return;
        }

        self::$themeRoot = WP_CONTENT_DIR . '/themes/' . $themeName;

        require self::$themeRoot . '/vendor/autoload.php';
        self::initContainer();
    }

    /**
     * Configure and register Nette RobotLoader for WPLokerBJM classes.
     *
     * Replaces the previous Composer classmap approach. The RobotLoader
     * scans the theme's server/ directory and this MU plugin's directory
     * for PHP classes, building a cached index.
     *
     * In production, uses the cached index for zero parsing overhead.
     */
    private static function setupRobotLoader(RobotLoader $robotLoader = new RobotLoader()): RobotLoader
    {
        $robotLoader->addDirectory(Bootstrap::$themeRoot . '/server/');
        $robotLoader->addDirectory(__FILE__);
        $robotLoader->setCacheDirectory(Bootstrap::$themeRoot . '/cache/robotloader/');
        $robotLoader->setAutoRefresh(defined('WP_ENV') && WP_ENV === 'development');
        $robotLoader->reportParseErrors(defined('WP_DEBUG') && WP_DEBUG);
        $robotLoader->register();

        return $robotLoader;
    }
    /**
     * Build the PHP-DI container and run theme initialization.
     */
    private static function initContainer(): void
    {
        try {
            $rl = self::setupRobotLoader();
            $c = new WPLokerBJMContainer(robotLoader: $rl, cacheDir: \sprintf('%s/cache', self::$themeRoot, '/'))
                ->initContainerBuilder()
                ->buildContainer();
            $c->set(RobotLoader::class, $rl);
            $init = $c->get(Init::class);
            $init->initialize();
        } catch (\Exception $e) {
            error_log('wplokerbjm Bootstrap error: ' . $e->getMessage());
        }
    }
}
// *Only auto-boot in WordPress context. Tests and CLI tools define
// *WPLOKERBJM_TEST_ENV to load the class without executing boot().
!defined('WPLOKERBJM_TEST_ENV')  &&  Bootstrap::boot('wplokerbjm');
