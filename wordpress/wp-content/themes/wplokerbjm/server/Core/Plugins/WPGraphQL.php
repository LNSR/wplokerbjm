<?php

namespace WPLokerBJM\Core\Plugins;

use DI\Attribute\Injectable;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};
use WPLokerBJM\Core\Container\Support\WPHooksRegistry;

/**
 * WPGraphQL-related hooks extracted from GlobalHooks.
 * @phpstan-import-type GraphQLDataType from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
 */
#[Injectable(lazy: true)]
class WPGraphQL
{

    public static function isActive(): bool
    {
        return PluginList::WpGraphql->isActive();
    }

    public function __construct(private WPHooksRegistry $hookRegistry, private LiteSpeedGraphQLIntegration $litespeedGraphQLIntegration)
    {
        if (self::isActive())
            return;
        $hookRegistry->unregisterByClass(self::class);
        $hookRegistry->unregisterDeferredByClass(self::class);
    }

    /**
     * Inject the JWT from the HttpOnly cookie as a Bearer token so the JWT
     * authentication plugin can authenticate the request transparently.
     */
    #[Action('graphql_init')]
    public function injectJwtFromCookie(): void
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

    /**
     * Disable nonce check for our origins
     * @param bool $requireNonce default true according
     * @see \WPGraphQL\Router::validate_http_request_authentication
     */
    #[Filter('graphql_cookie_auth_require_nonce')]
    public function disableRequireNonce(bool $requireNonce): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'];

        if (empty($origin))
            return $requireNonce;


        $result = \in_array($origin, $this->allowedOrigins(), true);
        return $result ? false : $requireNonce;
    }

    /**
     * Authenticate GraphQL requests
     * * Must be logged in Wordpress to have the cookies, but this allows GraphQL requests to be authenticated for decoupled frontend.
     */
    #[Action('init_graphql_request')]
    public function authenticateViaCookie(): void
    {
        $cookieValue = '';
        $cookieName = '';

        // Prefer the normal PHP cookie superglobal and detect WP login cookies by name
        if (!empty($_COOKIE)) {
            foreach ($_COOKIE as $name => $val) {
                // support both the regular login and secure login cookies
                if (
                    str_starts_with($name, 'wordpress_logged_in_') ||
                    str_starts_with($name, 'wordpress_sec_')
                ) {
                    $cookieValue = wp_unslash($val);
                    $cookieName = $name;
                    break;
                }
            }
        }

        // If we still don't have it, try parsing the raw Cookie header for known WP login cookies
        if ($cookieValue === '' && !empty($_SERVER['HTTP_COOKIE'])) {
            $header = $_SERVER['HTTP_COOKIE'];
            if (preg_match('/(?:^|;\\s*)(wordpress_logged_in_[^=]+|wordpress_sec_[^=]+)=([^;]+)/', $header, $m2)) {
                $cookieName = $m2[1] ?? '';
                $cookieValue = rawurldecode($m2[2]);
            }
        }

        if ($cookieValue === '') {
            return;
        }

        // Validate the cookie value using WP helper
        // choose scheme based on cookie type: secure login cookies use the secure_auth scheme
        $scheme = str_starts_with($cookieName, 'wordpress_sec_') ? 'secure_auth' : 'logged_in';
        $user_id = wp_validate_auth_cookie($cookieValue, $scheme);
        if ($user_id) {
            wp_set_current_user((int) $user_id);
            wp_get_current_user();
        } else {
            Logger::error('AuthDebug', 'authenticateViaCookie validation FAILED for cookie_name=' . $cookieName);
        }
    }

    /**
     * @return array
     */
    private function allowedOrigins(): array
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $officalOrigins = [
            'https://dev.lokerbanjarmasin.my.id',
            'https://staging.lokerbanjarmasin.my.id',
            'https://lokerbanjarmasin.my.id',
            'https://wp.lokerbanjarmasin.my.id',
        ];
        
        $allowed = [];
        if (!SharedUtils::isDevelopment())
            return $officalOrigins;

        $parts = wp_parse_url($origin);
        if (
            ($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') === 'localhost'
        ) {
            $allowed[] = $origin;
        }
        return array_merge($officalOrigins, $allowed);
    }

    /**
     * Restricts GraphQL CORS to same origin for security and adds X-WP-Nonce for logged-in users.
     */
    #[Filter('graphql_response_headers_to_send', 12)]
    public function ModifyHeaderGraphQL(array $headers): array
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $isAllowed = in_array($origin, $this->allowedOrigins(), true);

        if ($isAllowed) {
            $headers['Access-Control-Allow-Origin'] = $origin;
        }

        if (is_user_logged_in()) {
            $headers['Logged-In'] = 'true';
        }

        $removeDuplicate = static function ($headerValue) {
            return $headerValue
                |> (static fn($v) => explode(',', $v))
                |> (static fn($v) => array_map('trim', $v))
                |> array_filter(...)
                |> array_unique(...)
                |> (static fn($v) => implode(', ', $v));
        };

        $headers['Access-Control-Allow-Credentials'] = 'true';

        $headers['Access-Control-Allow-Headers'] = ($headers['Access-Control-Allow-Headers'] ?? '') . ', X-WP-Nonce, If-None-Match, If-Match, Authorization';
        $headers['Access-Control-Allow-Headers'] = $removeDuplicate($headers['Access-Control-Allow-Headers']);

        $headers['Access-Control-Expose-Headers'] = 'X-WP-Nonce, ETag';
        $headers['Access-Control-Max-Age'] = '86400';
        $headers['Vary'] = ($headers['Vary'] ?? '') . ', Origin, Authorization';
        $headers['Vary'] = $removeDuplicate($headers['Vary']);

        $headers = $this->litespeedGraphQLIntegration->addTagResponses($headers);
        unset($headers['Expires']);

        if (isset($headers['Last-Modified']) && empty($headers['Last-Modified'])) {
            unset($headers['Last-Modified']);
        }

        if (!is_user_logged_in())
            return $headers = $this->applyCachePolicy($headers);

        /** 
         * @see WPGraphQL::applyCachePolicy 
         * 'graphql_send_nocache_headers' filter might be overridden by Litespeed hence we better target 'nocache_headers' instead
         */
        $this->hookRegistry->activateDeferredByMethod(self::class, 'applyCachePolicy');
        return $headers;
    }

    /**
     * Override default no-store header
     */
    #[Filter('nocache_headers', 12, defer: true)]
    public function applyCachePolicy(array $headers): array
    {
        $loggedIn = is_user_logged_in();
        $cacheValue = $loggedIn
            ? 'private, max-age=360, must-revalidate'
            : 'public, max-age=3600, stale-while-revalidate=86400';

        $headers['Cache-Control'] = $cacheValue;
        return $headers;
    }

    /**
     * @see \WPGraphQL\Router::prepare_headers;
     */
    #[Filter('graphql_response_status_code', 11, 2)]
    public function setGraphQLResponseStatusCode(
        int $http_status_code,
        mixed $graphql_response,
    ): int {

        if ($graphql_response instanceof \GraphQL\Executor\ExecutionResult) {
            /** @var GraphQLDataType $data */
            $data = $graphql_response->data ?? null;
            if (is_array($data) && array_key_exists('jwt', $data) && $data['jwt'] === null) {
                return 401;
            }
        }

        return $http_status_code;
    }

    /**
     * @see get_graphql_setting
     * @see \WPGraphQL\Admin\Settings\Settings::register_settings() -> public_introspection_enabled
     * @see \WPGraphQL\SmartCache\Admin\Settings::init()
     */
    #[Filter('graphql_get_setting_section_field_value', 11, 3)]
    public function setPublicIntrospection($value, $default_value, $option_name)
    {
        if ($option_name === 'public_introspection_enabled') {
            if (defined('WP_ENV') && WP_ENV !== 'production')
                return 'on';
            return 'off';
        }
        return $value;
    }
}
