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
use WPLokerBJM\Core\Wordpress\ContainerRegistryHookEvent;
use WPLokerBJM\Core\Wordpress\InstanceRuntimeRegistryHookEvent;
use WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\Integrations\LiteSpeedGraphQLIntegration;
use WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\Integrations\LitespeedIntegrationCommands;
use WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\WPRestJWTHooks;
use WPLokerBJM\Core\Wordpress\Utilities\WPCookieUtils;
use WPLokerBJM\Services\Auth\JWT\JWTService;
use WPLokerBJM\Services\GraphQL\ETag\WPGraphQLETag;
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
        do_action(ContainerRegistryHookEvent::ACTIVATE_DEFERRED_BY_TAGS, ['graphql']);

        $modules = [
            $this->graphQlPluginSettings,
            $this->bootGraphqlRequest,
        ];

        foreach ($modules as $module) {
            do_action(InstanceRuntimeRegistryHookEvent::REGISTER_HOOKS, $module);
        }
    }
    
    #region GraphQlPluginSettings
    /**
     * @phpstan-ignore-next-line
     * @var __CLASS__::class
     */
    private ?ModuleClassHookMetadata $graphQlPluginSettings {
        get {
            return $this->graphQlPluginSettings ??= new class(__CLASS__, __PROPERTY__) extends ModuleClassHookMetadata {
                /**
                 * @see get_graphql_setting
                 * @see \WPGraphQL\Admin\Settings\Settings::register_settings() -> {
                 *  public_introspection_enabled,
                 *  debug_mode_enabled,
                 * }
                 * @see \WPGraphQL\SmartCache\Admin\Settings::init()
                 * @param 'public_introspection_enabled'|'debug_mode_enabled' $option_name
                 */
                #[Filter('graphql_get_setting_section_field_value', \PHP_INT_MAX, 3)]
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
    }
    
    #endregion

    #region GraphQL Boot

    /**
     * @phpstan-ignore-next-line
     * @var __CLASS__::class
     */
    private ?ModuleClassHookMetadata $bootGraphqlRequest {
        get {
            return $this->bootGraphqlRequest ??= new class(__CLASS__, __PROPERTY__) extends ModuleClassHookMetadata {
                #[Inject]
                private LiteSpeedGraphQLIntegration $liteSpeedIntegration;
                #[Inject]
                private WPCookieUtils $cookieUtils;

                #[Inject([WPGraphQLETag::class, 'checkEarly304AndExit'], lazy: true)]
                private \Closure $checkEarly304AndExit;

                #[Inject([JWTService::class, 'injectJwtFromCookie'], lazy: true)]
                private \Closure $injectJwtFromCookie;


                /**
                 * Unified init request handler: checks ETag cache before performing auth.
                 */
                #[Action('init_graphql_request', once: true)]
                public function handleInitRequest(): void
                {
                    $this->liteSpeedIntegration->liteSpeedCommands?->setCacheable();
                    $this->authenticateViaCookie();
                    ($this->checkEarly304AndExit)();
                }

                /**
                 * Authenticate GraphQL requests
                 * Must be logged in Wordpress to have the cookies, but this allows GraphQL requests to be authenticated for decoupled frontend.
                 */
                private function authenticateViaCookie(): void
                {
                    $cookie = $this->cookieUtils->WordpressAuthCookieInfo;
                    if (empty($cookie->name)) return;

                    ($this->injectJwtFromCookie)();

                    $scheme = str_starts_with($cookie->name, 'wordpress_sec_') ? 'secure_auth' : 'logged_in';
                    $user_id = \wp_validate_auth_cookie($cookie->value, $scheme);
                    if ($user_id === false) return;

                    \wp_set_current_user((int) $user_id);
                    \wp_get_current_user();
                }
            };
        }
    }

    #endregion
}
