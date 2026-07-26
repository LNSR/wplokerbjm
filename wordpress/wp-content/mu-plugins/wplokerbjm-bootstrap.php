<?php
declare(strict_types=1);
namespace WPLokerBJM;

use Nette\Loaders\RobotLoader;
use WPLokerBJM\Core\Theme\ThemeInject;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\AutowireScanner;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksScanner;
use WPLokerBJM\Core\Container\Init;
use WPLokerBJM\Core\Container\WPLokerBJMContainer;

// Exit if accessed directly for security
!defined('ABSPATH') && exit;

/**
 * Bootstraps the wplokerbjm theme — sets up RobotLoader class autoloading
 * and initializes the PHP-DI container early in the WordPress lifecycle.
 *
 * Runs as an MU plugin so hooks are registered before theme activation.
 *
 * @see ThemeInject
 */
class Bootstrap
{
    private static ?RobotLoader $robotLoader = null;

    /**
     * Entry point. Called once from this file after the class definition.
     */
    public static function boot(): void
    {
        $theme = 'wplokerbjm';
        if (get_stylesheet() !== $theme) {
            return;
        }

        $themeRoot = WP_CONTENT_DIR . '/themes/' . $theme;
        require_once $themeRoot . '/vendor/autoload.php';

        self::setupRobotLoader($themeRoot);
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
    private static function setupRobotLoader(string $themeRoot): void
    {
        $rl = new RobotLoader;
        $rl->addDirectory($themeRoot . '/server/');
        $rl->addDirectory(__DIR__);
        $rl->setTempDirectory($themeRoot . '/cache/robotloader/');
        $rl->setAutoRefresh(defined('WP_ENV') && WP_ENV === 'development');
        $rl->reportParseErrors(defined('WP_DEBUG') && WP_DEBUG);
        $rl->register();

        self::$robotLoader = $rl;
    }

    /**
     * Expose RobotLoader for use by @see AutowireScanner, @see WPHooksScanner, etc.
     */
    public static function getRobotLoader(): RobotLoader
    {
        return self::$robotLoader;
    }

    /**
     * Inject a RobotLoader instance — used by tests to share the test
     * autoloader with @see AutowireScanner and @see WPHooksScanner.
     */
    public static function setRobotLoader(RobotLoader $rl): void
    {
        self::$robotLoader = $rl;
    }

    /**
     * Build the PHP-DI container and run theme initialization.
     */
    private static function initContainer(): void
    {
        try {
            /** @var Init $init */
            $init = WPLokerBJMContainer::getContainer()->get(Init::class);
            $init->initialize();
        } catch (\Exception $e) {
            error_log('wplokerbjm Bootstrap error: ' . $e->getMessage());
        }
    }
}

// *Only auto-boot in WordPress context. Tests and CLI tools define
// *WPLOKERBJM_SKIP_BOOT to load the class without executing boot().
if (!defined('WPLOKERBJM_SKIP_BOOT')) {
    Bootstrap::boot();
}