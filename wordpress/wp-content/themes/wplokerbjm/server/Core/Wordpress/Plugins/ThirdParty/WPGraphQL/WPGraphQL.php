<?php

namespace WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\WPGraphQL;

use GraphQL\Executor\ExecutionResult;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Wordpress\Plugins\PluginConfigInterface;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter, Inject};
use WPLokerBJM\Core\Wordpress\Plugins\PluginList;
use WPLokerBJM\Shared\Utilities\{SharedUtils};
use WP_User;
use WPLokerBJM\Core\Wordpress\ContainerRegistryEvent;
use WPLokerBJM\Core\Wordpress\InstanceRuntimeRegistryEvent;
use WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\Integrations\LiteSpeedGraphQLIntegration;
use WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\WPGraphQL\Services\WPGraphQLETag;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * WPGraphQL-related hooks extracted from GlobalHooks.
 * @phpstan-import-type GraphQLDataType from GraphQLRegistration
 */
final class WPGraphQL implements PluginConfigInterface
{
    public static function isActive(): bool
    {
        return PluginList::WpGraphql->isActive();
    }


    #[Action('graphql_init', once: true)]
    private function boot(): void
    {
        do_action(ContainerRegistryEvent::ACTIVATE_DEFERRED_BY_TAGS, ['graphql']);
        do_action(InstanceRuntimeRegistryEvent::REGISTER_HOOKS, $this->graphQlPluginSettings);
    }
    
    #region GraphQlPluginSettings
    /**
     * @phpstan-ignore-next-line
     * @var __CLASS__::class
     */
    private ModuleClassHookMetadata $graphQlPluginSettings {
        get => $this->graphQlPluginSettings ??= new class(__CLASS__, __PROPERTY__) extends ModuleClassHookMetadata {
            /**
             * @see get_graphql_setting
             * @see \WPGraphQL\Admin\Settings\Settings::register_settings() -> {
             *  public_introspection_enabled,
             *  debug_mode_enabled,
             * }
             * @see \WPGraphQL\SmartCache\Admin\Settings::init()
             * @param 'public_introspection_enabled'|'debug_mode_enabled' $option_name
             */
            #[Filter('graphql_get_setting_section_field_value', \PHP_INT_MAX, 3, executeIf: static function (string $option_name): bool {
                return \in_array($option_name, ['public_introspection_enabled', 'debug_mode_enabled'], true);
            })]
            public function setSettings(?string $value, ?string $default_value, string $option_name): mixed
            {
                return match ($option_name) {
                    'public_introspection_enabled' => SharedUtils::isDevelopment() ? 'on' : 'off',
                    'debug_mode_enabled' => SharedUtils::isDevelopment() ? 'on' : 'off',
                    default => $value,
                };
            }
        };
    }

    #endregion
}
