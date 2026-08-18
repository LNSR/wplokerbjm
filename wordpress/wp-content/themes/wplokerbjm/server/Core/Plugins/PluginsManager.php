<?php
namespace WPLokerBJM\Core\Plugins;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksRuntimeRegistry};
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyInjector;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\AnonClassHookMetadata;
use WPLokerBJM\Shared\Utilities\{PluginList, SharedUtils};
use WPLokerBJM\Core\Plugins\ThirdParty\{
    Litespeed,
    LiteSpeedGraphQLIntegration,
    MetaBox,
    Rankmath,
    WPRestJWTHooks,
    WPGraphQL
};
use WPLokerBJM\Shared\Log\Logger;

/* ==========================================================================
   INTERFACE CONFIG
   ========================================================================== */
interface PluginConfigInterface
{
    public static function isActive(): bool;
}

/* ==========================================================================
   PLUGIN MANAGEMENT
   ========================================================================== */
class PluginManagement
{
    public const MUST_HAVE_PLUGINS = [
        PluginList::LiteSpeed->value,
        PluginList::WpGraphql->value,
        PluginList::RankMath->value,
        PluginList::MetaBoxLite->value,
        PluginList::MetaBox->value,
    ];

    /**
     * List of integration classes that implement PluginConfigInterface.
     * 
     * @var array<class-string<PluginConfigInterface>>
     * @phpstan-assert-if-true PluginConfigInterface
     */
    public const THIRD_PARTY_INTEGRATIONS = [
        Litespeed::class,
        LiteSpeedGraphQLIntegration::class,
        MetaBox::class,
        Rankmath::class,
        WPRestJWTHooks::class,
        WPGraphQL::class,
    ];

    public function __construct(private WPHooksContainerRegistry $hooksRegistry, private WPHooksRuntimeRegistry $hooksRuntimeRegistry) {}

    /**
     * Purge all registered and deferred hooks for third-party integrations 
     * whose underlying WordPress plugins are inactive.
     *
     */
    #[Action('muplugins_loaded', 0, once: true)]
    public function unregisterInactivePluginHooks(): void
    {
        foreach (self::THIRD_PARTY_INTEGRATIONS as $integrationClass) {
            $isActive = $integrationClass::isActive();
            if ($isActive) {
                continue;
            }
            Logger::warning("Plugin status", $integrationClass . ' is ' . ($isActive ? 'active' : 'inactive'));

            $this->hooksRegistry->unregisterByClass($integrationClass);
            $this->hooksRegistry->unregisterDeferredByClass($integrationClass);
        }
    }
    /**
     * Remove the "Deactivate" action link for required plugins.
     */
    #[Filter('plugin_action_links', 4, 2, once: true)]
    public function lockPluginActionLinks(array $actions, string $pluginFile): array
    {
        if (in_array($pluginFile, self::MUST_HAVE_PLUGINS) && isset($actions['deactivate'])) {
            unset($actions['deactivate']);

            $actions['required'] = '<span style="color: red; font-weight: bold;">Must Have Dependency</span>';
        }

        return $actions;
    }

    #region 3rd party choice hooks
    #[Filter('option_active_plugins', once: true, registerIf: static function () {
            return empty($_SERVER['REQUEST_URI']) || !str_contains($_SERVER['REQUEST_URI'], \get_option('graphql_endpoint') ?: '/graphql') && !\is_admin();
            })]
    public function disableWpGraphqlPlugin(array $plugins): array
    {
        unset($plugins[array_search(PluginList::WpGraphql->value, $plugins, true)]);
        $this->hooksRegistry->unregisterByClass(WPGraphQL::class);
        $this->hooksRegistry->unregisterDeferredByClass(WPGraphQL::class);
        return $plugins;
    }

    #[Filter('option_active_plugins', once: true,
        registerIf: static function (): bool {
                    if (is_admin()) {
                    $action = $_REQUEST['action'] ?? '';
                        if (in_array($action, ['upgrade-plugin', 'update-plugin', 'activate', 'deactivate', 'activate-plugin'], true)) {
                        return true;
                        }
                    }
                $cookie = SharedUtils::getWordpressAuthCookie();
                    if ((!\is_admin() || \wp_doing_cron() || \wp_doing_ajax() || SharedUtils::isWPCLI()) && empty($cookie['name']))
                    return true;
                return false;
                }
    )]
    public function disableQueryMonitorPlugin(array $plugins): array
    {
        define('QM_DISABLED', true);
        unset($plugins[array_search(PluginList::QueryMonitor->value, $plugins, true)]);
        return $plugins;
    }

    #endregion


    #region filter hooks sequence
    /**
     * activation hooks steps
     * @var static::class
     */
    #[Action('muplugins_loaded', once: true)]
    public private(set) AnonClassHookMetadata $pluginEnvironmentCheck {
        get => $this->pluginEnvironmentCheck ??= new class (self::class, __PROPERTY__, $this->hooksRuntimeRegistry) extends AnonClassHookMetadata {

            public function __construct(
            $parentClass,
            $parentProperty,
            private WPHooksRuntimeRegistry $runtimeRegistry,
            ) {
                parent::__construct($parentClass, $parentProperty);
            }
            public function __invoke()
            {
                $this->runtimeRegistry->registerHooksOn($this);
            }

            /**
             * Temporarily disable specific plugins if in development environment.
             */
            #[Filter('option_active_plugins', 0, once: true,
            registerIf: static function (): bool {
                            return SharedUtils::isDevelopment();
                        }
            )]
            public function disablePluginsForDevImpl(array $plugins): array
            {
                $pluginsToDisable = $this->listPluginsToDisable();
                return $this->filteredPlugins($plugins, $pluginsToDisable);
            }


            /**
             * Temporarily disable specific plugins if simulating production environment on local machine.
             */
            #[Filter('option_active_plugins', 1, once: true,
            registerIf: static function (): bool {
                            return !SharedUtils::isDevelopment() && SharedUtils::isLocalhost();
                        }
            )]
            public function disablePluginsforSimulatedProdImpl(array $plugins): array
            {
                $pluginsToDisable = $this->listPluginsToDisable();

                return $this->filteredPlugins($plugins, $pluginsToDisable);
            }

            /**
             * Force active plugins for production
             */
            #[Filter('option_active_plugins', 2, once: true,
            registerIf: static function (): bool {
                            return !SharedUtils::isDevelopment();
                        }
            )]
            public function forceActivePlugin(array $plugins): array
            {
                foreach (PluginManagement::MUST_HAVE_PLUGINS as $plugin) {
                    if (!in_array($plugin, $plugins)) {
                        array_push($plugins, $plugin);
                    }
                }
                return $plugins;
            }


            /**
             * Returns the list of plugins to disable, optionally merged with extra plugins.
             *
             * @param array|null $extra Optional array of additional plugin prefixes to disable.
             * @return array Array of plugin prefixes to disable.
             */
            private function listPluginsToDisable(?array $extra = []): array
            {
                // Subject to change
                static $base = [
                'wordfence/',
                'tinywp-mobile-detect/',
                'fast-indexing-api/',
                ];
                return array_merge($base, $extra);
            }
            /**
             * Filters the list of active plugins by removing specified plugins.
             *
             * @param array $plugins          Array of active plugin file paths.
             * @param array $pluginsToDisable Array of plugin prefixes to disable.
             * @return array Filtered array of active plugins.
             */
            private function filteredPlugins(array $plugins, array $pluginsToDisable): array
            {
                $filtered = array_filter($plugins, static function (string $plugin) use ($pluginsToDisable): bool {
                    foreach ($pluginsToDisable as $disable) {
                        if (str_starts_with($plugin, $disable)) {
                            return false;
                        }
                    }
                    return true;
                });

                return array_values($filtered);
            }
        };
    }
    #endregion

}