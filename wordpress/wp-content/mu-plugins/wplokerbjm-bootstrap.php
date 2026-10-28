<?php

declare(strict_types=1);

namespace WPLokerBJM;

use InvalidArgumentException;
use LogicException;
use Nette\Loaders\RobotLoader;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Wordpress\Theme\ThemeHooks;
use WPLokerBJM\Core\Container\Init;
use WPLokerBJM\Core\Container\WPLokerBJMContainer;
use WPLokerBJM\Shared\Log\Logger;

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
class WPLokerBJMBootstrap
{
    public protected(set) static bool $alreadyLoaded = false;
    public protected(set) string $themeRoot;
    public protected(set) string $nameMUpluginFile = __FILE__;
    protected ContainerInterface $container;
    public protected(set) RobotLoader $robotloader;

    public function __construct(string $themeName)
    {
        if (self::$alreadyLoaded) throw new LogicException(sprintf('%s already initialized', self::class));
        if (\get_stylesheet() !== $themeName) throw new LogicException(sprintf('Theme name %s does not match %s', \get_stylesheet(), $themeName));
        self::$alreadyLoaded = true;

        $this->themeInfo = $themeName;
        require_once $this->themeInfo->themeRoot . '/vendor/autoload.php';
        $this->robotloader = new RobotLoader()
            ->addDirectory($this->themeInfo->themeRoot . '/server/')
            ->setCacheDirectory($this->themeInfo->themeRoot . '/cache/robotloader/')
            ->setAutoRefresh(defined('WP_ENV') && WP_ENV === 'development')
            ->reportParseErrors(defined('WP_DEBUG') && WP_DEBUG)
            ->register();

        $this->init();
    }

    /** @var ?__WPLokerBJMThemeInfo */
    public private(set) object|string|null $themeInfo {
        set {
            $this->themeInfo = new class(
                \is_string($value)
                    ? $value
                    : throw new InvalidArgumentException(
                        'Theme name must be a string.'
                    )
            ) {
                public readonly string $themeRoot;

                public function __construct(public readonly string $themeName)
                {
                    $this->themeRoot = WP_CONTENT_DIR . '/themes/' . $themeName;
                    return;
                    /**
                     * Double dead code alias, self::class seem more reliable for intelephense LSP, but cause PHP(PHP0413) warning
                     * __CLASS__ ones help silence the warning
                     * !Important for full autocomplete IDE by tricking LSP to use virtual class, no need for interface or abstract class
                     * !Make sure to use object type and attach @var/@param phpdoc for autocomplete if used without accessing this class
                     */
                    \class_alias(self::class, __WPLokerBJMThemeInfo::class);
                    \class_alias(__CLASS__, __WPLokerBJMThemeInfo::class);
                }

                /**
                 * Check whether theme match with this bootstrap
                 */
                public function checkThemeMatch(string $themeName): bool
                {
                    if ($this->themeName !== $themeName) {
                        \error_log(sprintf('Theme name %s does not match %s; current wordpress set theme is %s', $this->themeName, $themeName, \get_stylesheet()));
                    }
                    return $this->themeName === $themeName;
                }
            };
        }
    }

    protected function init(): void
    {
        try {
            $this->container = new WPLokerBJMContainer(
                robotLoader: $this->robotloader,
                cacheDir: \sprintf('%s/cache', $this->themeInfo->themeRoot)
            )
                ->initContainerBuilder()
                ->setExtraRuntimeDefinitionsSet([
                    RobotLoader::class => $this->robotloader,
                    WPLokerBJMBootstrap::class => $this
                ])
                ->buildContainer();
            $this->container
                ->get(Init::class)
                ->initialize();
        } catch (\Throwable $e) {
            error_log('MU-Plugin bootstrap fail' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
new WPLokerBJMBootstrap(themeName: 'wplokerbjm');
