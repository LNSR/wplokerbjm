<?php

namespace WPLokerBJM\Core\Plugins\ThirdParty\WPGraphQL;

use GraphQL\Executor\ExecutionResult;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\AnonClassHookMetadata;
use WPLokerBJM\Core\Plugins\PluginConfigInterface;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter, Inject};
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};
use WP_User;
use WPLokerBJM\Core\DependencyInjectorHookActions;
use WPLokerBJM\Core\HooksRuntimeRegistryActions;
use WPLokerBJM\Core\Plugins\ThirdParty\Integrations\LiteSpeedGraphQLIntegration;
use WPLokerBJM\Core\Plugins\ThirdParty\WPGraphQL\Services\WPGraphQLETag;

/**
 * WPGraphQL-related hooks extracted from GlobalHooks.
 * @phpstan-import-type GraphQLDataType from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
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
        do_action(DependencyInjectorHookActions::INJECT_ON, $this->graphqlInit);
        do_action(DependencyInjectorHookActions::INJECT_ON, $this->headersPolicy);
        do_action(DependencyInjectorHookActions::INJECT_ON, $this->graphQlResponse);
        //register hooks on runtime registry
        do_action(HooksRuntimeRegistryActions::REGISTER_HOOKS, $this->graphqlInit);
        do_action(HooksRuntimeRegistryActions::REGISTER_HOOKS, $this->graphQlPluginSettings);
        do_action(HooksRuntimeRegistryActions::REGISTER_HOOKS, $this->headersPolicy);
        do_action(HooksRuntimeRegistryActions::REGISTER_HOOKS, $this->graphQlResponse);
    }

    #region GraphQL Init
    /**
     * @var static::class
     */
    private AnonClassHookMetadata $graphqlInit {
        get => $this->graphqlInit ??= new class(self::class, __PROPERTY__) extends AnonClassHookMetadata {
            #[Inject]
            private LiteSpeedGraphQLIntegration $litespeedGraphQLIntegration;
            #[Inject]
            private WPGraphQLETag $eTag;

            /**
             * Unified init request handler: checks ETag cache before performing auth.
             */
            #[Action('init_graphql_request', once: true)]
            public function handleInitRequest(): void
            {
                $this->litespeedGraphQLIntegration->setCacheable();
                $this->authenticateViaCookie();
                $this->eTag->checkEarly304();
            }

            /**
             * Authenticate GraphQL requests
             * Must be logged in Wordpress to have the cookies, but this allows GraphQL requests to be authenticated for decoupled frontend.
             */
            private function authenticateViaCookie(): void
            {
                $cookie = SharedUtils::getWordpressAuthCookie();
                if (empty($cookie))
                    return;
                $this->injectJwtFromCookie();

                // Validate the cookie value using WP helper
                // choose scheme based on cookie type: secure login cookies use the secure_auth scheme
                $scheme = str_starts_with($cookie['name'], 'wordpress_sec_') ? 'secure_auth' : 'logged_in';
                $user_id = wp_validate_auth_cookie($cookie['value'], $scheme);
                if ($user_id !== false) {
                    wp_set_current_user((int) $user_id);
                    wp_get_current_user();
                }
            }
            /**
             * Inject the JWT from the HttpOnly cookie as a Bearer token so the JWT
             * authentication plugin can authenticate the request transparently.
             */
            private function injectJwtFromCookie(): void
            {
                try {
                    if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_COOKIE['jwt-token'])) {
                        $bearer = 'Bearer ' . $_COOKIE['jwt-token'];
                        $_SERVER['HTTP_AUTHORIZATION'] = $bearer;
                        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $bearer;
                    }
                } catch (\Exception $e) {
                    Logger::error('AuthDebug', 'injectJwtFromCookie error: ' . $e->getMessage());
                }
            }
        };
    }

    #endregion

    #region Header stuff
    /**
     * @var static::class
     */
    private AnonClassHookMetadata $headersPolicy {
        get => $this->headersPolicy ??= new class(self::class, __PROPERTY__) extends AnonClassHookMetadata {
            #[Inject]
            private LiteSpeedGraphQLIntegration $litespeedGraphQLIntegration;
            #[Inject]
            private WPGraphQLETag $eTag;
            private const OFFICIAL_ORIGINS = [
                'https://dev.lokerbanjarmasin.my.id',
                'https://staging.lokerbanjarmasin.my.id',
                'https://lokerbanjarmasin.my.id',
                'https://wp.lokerbanjarmasin.my.id',
            ];

            /**
             * Restricts GraphQL CORS to same origin for security and adds X-WP-Nonce for logged-in users.
             */
            #[Filter('graphql_response_headers_to_send', 9)]
            public function ModifyHeaderGraphQL(array $headers): array
            {
                /**
                 * @see WPGraphQL\SmartCache\Cache\Results::init
                 * remove WPGraphQL author hooks and inbuit core nocache Headers
                 */
                \remove_all_filters('graphql_response_headers_to_send');
                $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

                if (in_array($origin, $this->allowedOrigins(), true)) {
                    $headers['Access-Control-Allow-Origin'] = $origin;
                }

                // Headers relevant to both preflight and actual responses.
                $headers['Access-Control-Allow-Credentials'] = 'true';
                $headers['Access-Control-Allow-Headers'] =
                    ($headers['Access-Control-Allow-Headers'] ?? '') .
                    ', X-WP-Nonce, If-None-Match, If-Match, Authorization';
                $headers['Access-Control-Max-Age'] = '86400';

                $headers['Access-Control-Allow-Headers'] = $this->removeDuplicateValues($headers['Access-Control-Allow-Headers']);

                //! Preflight ends here.
                if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
                    return $headers;
                }

                $headers = $this->eTag->setHeader($headers);

                $headers['Access-Control-Expose-Headers'] = 'X-WP-Nonce, ETag';

                $headers['Vary'] = ($headers['Vary'] ?? '') . ', Origin, Authorization';
                $headers['Vary'] = $this->removeDuplicateValues($headers['Vary']);

                $headers = $this->litespeedGraphQLIntegration->addTagResponses($headers);

                unset($headers['Expires']);

                if (empty($headers['Last-Modified'])) {
                    unset($headers['Last-Modified']);
                }

                $headers = $this->applyCachePolicy($headers);

                if (is_user_logged_in()) {
                    $headers['Logged-In'] = 'true';
                    $headers['X-WP-Nonce'] = graphql_get_nonce();
                }
                return $headers;
            }
            #[Filter(
                'graphql_send_nocache_headers',
                9,
                registerIf: static function (): bool {
                    return is_user_logged_in() && \remove_all_filters('graphql_send_nocache_headers');
                }
            )]
            public function disableGraphQLNocacheHeader(): bool
            {
                return false;
            }

            #[Filter('nocache_headers', 9, registerIf: static function (): bool {
                return is_user_logged_in() && \remove_all_filters('nocache_headers');
            })]
            public function applyCachePolicy(array $headers): array
            {
                $loggedIn = is_user_logged_in();
                $isDev = SharedUtils::isDevelopment();
                $cacheValue = match (true) {
                    $isDev => $loggedIn ? 'private, no-cache, must-revalidate' : 'public, no-cache, must-revalidate',
                    default => $loggedIn ? 'private, no-cache, must-revalidate' : 'public, max-age=60, stale-while-revalidate=3600',
                };
                $headers['Cache-Control'] = $cacheValue;
                return $headers;
            }

            private function removeDuplicateValues(string $key): string
            {
                return $key
                |> (static fn($v) => explode(',', $v))
                |> (static fn($v) => array_map('trim', $v))
                |> array_filter(...)
                |> array_unique(...)
                |> (static fn($v) => implode(', ', $v));
            }

            private function allowedOrigins(): array
            {
                $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
                $origins = self::OFFICIAL_ORIGINS;
                if (!SharedUtils::isDevelopment())
                    return $origins;

                $allowed = [];
                $parts = wp_parse_url($origin);
                if (
                    ($parts['scheme'] ?? '') === 'https'
                    && ($parts['host'] ?? '') === 'localhost'
                ) {
                    $allowed[] = $origin;
                }
                return array_merge($origins, $allowed);
            }
        };
    }
    #endregion
    #region GraphQlPluginSettings
    /**
     * @var static::class
     */
    private AnonClassHookMetadata $graphQlPluginSettings {
        get => $this->graphQlPluginSettings ??= new class(self::class, __PROPERTY__) extends AnonClassHookMetadata {
            /**
             * @see get_graphql_setting
             * @see \WPGraphQL\Admin\Settings\Settings::register_settings() -> public_introspection_enabled
             * @see \WPGraphQL\SmartCache\Admin\Settings::init()
             */
            #[Filter('graphql_get_setting_section_field_value', 11, 3)]
            public function setPublicIntrospection(string $value, string $default_value, string $option_name): string
            {
                if ($option_name === 'public_introspection_enabled') {
                    return SharedUtils::isDevelopment() ? 'on' : 'off';
                }
                return $value;
            }

            #[Filter('graphql_get_setting_section_field_value', 11, 3)]
            public function setDebugMode(string $value, string $default_value, string $option_name): string
            {
                if ($option_name === 'debug_mode_enabled') {
                    return SharedUtils::isDevelopment() ? 'on' : 'off';
                }
                return $value;
            }
        };
    }
    #endregion
    #region GraphQLResponse
    /**
     * @var static::class
     */
    private AnonClassHookMetadata $graphQlResponse {
        get => $this->graphQlResponse ??= new class(self::class, __PROPERTY__) extends AnonClassHookMetadata {
            #[Inject]
            private WPGraphQLETag $eTag;
            private ?ExecutionResult $responseResult;
            /**
             * @see \WPGraphQL\Router::prepare_headers;
             * Router passes: $status_code, $_deprecated, $response, $query, $operation_name, $variables, $user
             */
            #[Filter('graphql_response_status_code', 11, 7)]
            public function setGraphQLResponseStatusCode(
                int $http_status_code,
                ExecutionResult $graphql_response,
                mixed $_deprecated = null,
                string $query = '',
                string $operation_name = '',
                ?array $variables = null,
                ?WP_User $user = null,
            ): int {
                $this->responseResult = $graphql_response;
                $http_status_code = $this->checkJwt401Response($http_status_code);
                $this->eTag->computeAndStore($graphql_response, $query, $operation_name, (array) $variables, $user);
                return $http_status_code;
            }

            /**
             * Check JWT 401 implementation.
             * ? Candidate to move to another concern later
             * @param int $http_status_code
             * @return int
             */
            private function checkJwt401Response(
                int $http_status_code,
            ): int {
                /**
                 * @var GraphQLDataType $data
                 */
                $data = $this->responseResult->data ?? null;
                if (array_key_exists('jwt', $data) && !$data['jwt']) {
                    return 401;
                }
                return $http_status_code;
            }
        };
    }
    #endregion
}
