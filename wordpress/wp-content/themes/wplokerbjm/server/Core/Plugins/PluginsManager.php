<?php
namespace WPLokerBJM\Core\Plugins;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\{PluginList, SharedUtils};
use WPLokerBJM\Core\Plugins\ThirdParty\{
    Litespeed,
    LiteSpeedGraphQLIntegration,
    MetaBox,
    Rankmath,
    WPRestJWTHooks,
    WPGraphQL
};
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
    private const MUST_HAVE_PLUGINS = [
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
    private const THIRD_PARTY_INTEGRATIONS = [
        Litespeed::class,
        LiteSpeedGraphQLIntegration::class,
        MetaBox::class,
        Rankmath::class,
        WPRestJWTHooks::class,
        WPGraphQL::class,
    ];

    public function __construct(private WPHooksContainerRegistry $hooksRegistry)
    {
    }

    /**
     * Purge all registered and deferred hooks for third-party integrations 
     * whose underlying WordPress plugins are inactive.
     * 
     * Runs late on `plugins_loaded` (priority) after WP has fully loaded 
     * all active plugins and constants.
     */
    #[Action('plugins_loaded', 0)]
    public function unregisterInactivePluginHooks(): void
    {
        foreach (self::THIRD_PARTY_INTEGRATIONS as $integrationClass) {
            if ($integrationClass::isActive())
                continue;
            $this->hooksRegistry->unregisterByClass($integrationClass);
            $this->hooksRegistry->unregisterDeferredByClass($integrationClass);
        }
    }

    #[Filter('option_active_plugins', 0)]
    public function activePluginsCondition(array $plugins): array
    {
        $plugins = $this->disablePluginsForDevImpl($plugins);
        $plugins = $this->disablePluginsforSimulatedProdImpl($plugins);
        $plugins = $this->forceActivePlugin($plugins);
        return $plugins;
    }

    /**
     * Remove the "Deactivate" action link for required plugins.
     */
    #[Filter('plugin_action_links', 4, 2)]
    public function lockPluginActionLinks(array $actions, string $pluginFile): array
    {
        if (in_array($pluginFile, self::MUST_HAVE_PLUGINS) && isset($actions['deactivate'])) {
            unset($actions['deactivate']);

            $actions['required'] = '<span style="color: red; font-weight: bold;">Must Have Dependency</span>';
        }

        return $actions;
    }

    /**
     * Temporarily disable specific plugins if in development environment.
     */
    private function disablePluginsForDevImpl(array $plugins): array
    {
        $isDev = SharedUtils::isDevelopment();
        if (!$isDev) {
            return $plugins;
        }
        $extra = [
        ];

        $pluginsToDisable = $this->listPluginsToDisable($extra);
        return $this->filteredPlugins($plugins, $pluginsToDisable);
    }

    /**
     * Force active plugins
     */
    private function forceActivePlugin(array $plugins): array
    {
        if (SharedUtils::isDevelopment())
            return $plugins;
        foreach (self::MUST_HAVE_PLUGINS as $plugin) {
            if (!in_array($plugin, $plugins)) {
                array_push($plugins, $plugin);
            }
        }
        return $plugins;
    }

    /**
     * Temporarily disable specific plugins if simulating production environment on local machine.
     */
    private function disablePluginsforSimulatedProdImpl(array $plugins): array
    {
        $isDev = !SharedUtils::isDevelopment() && SharedUtils::isLocalhost();

        $pluginsToDisable = $isDev ? $this->listPluginsToDisable() : [];

        return $this->filteredPlugins($plugins, $pluginsToDisable);
    }

    #region utilitiesHelper

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
    #endregion

}