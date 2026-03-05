<?php

namespace WPLokerBJM\Core\Plugins;

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * WPGraphQL-related hooks extracted from GlobalHooks.
 */
class WPGraphQL
{
    /**
     * Inject the JWT from the HttpOnly cookie as a Bearer token so the JWT
     * authentication plugin can authenticate the request transparently.
     */
    #[Action('graphql_init')]
    public static function injectJwtFromCookie(): void
    {
        if (!SharedUtils::isWPGraphQLActive()) {
            return;
        }

        if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_COOKIE['jwt-token'])) {
            $bearer = 'Bearer ' . $_COOKIE['jwt-token'];
            $_SERVER['HTTP_AUTHORIZATION'] = $bearer;
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $bearer;
        }
    }

    /**
     * Authenticate GraphQL requests
     * * Must be logged in Wordpress to have the cookies, but this allows GraphQL requests to be authenticated for decoupled frontend.
     */
    #[Action('init_graphql_request')]
    public static function authenticateViaCookie(): void
    {
        if (!SharedUtils::isWPGraphQLActive()) {
            return;
        }
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
     * Restricts GraphQL CORS to same origin for security and adds X-WP-Nonce for logged-in users.
     */
    #[Filter('graphql_response_headers_to_send')]
    public static function ModifyHeaderGraphQL(array $headers): array
    {
        if (!SharedUtils::isWPGraphQLActive()) {
            return $headers;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $allowList = [
            'https://dev.lokerbanjarmasin.my.id',
            'https://staging.lokerbanjarmasin.my.id',
            'https://lokerbanjarmasin.my.id',
            'https://wp.lokerbanjarmasin.my.id',
            'https://localhost:3000',
            'https://localhost:5173',
            'https://localhost:8173',
            'https://localhost:4173',
        ];

        if (in_array($origin, $allowList, true)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
        }

        $headers['Access-Control-Allow-Credentials'] = 'true';

        $headers['Access-Control-Allow-Headers'] = ($headers['Access-Control-Allow-Headers'] ?? '') !== ''
            ? $headers['Access-Control-Allow-Headers'] . ', X-WP-Nonce'
            : 'X-WP-Nonce';
        $headers['Access-Control-Expose-Headers'] = 'X-WP-Nonce';
        $loggedIn = is_user_logged_in();
        if ($loggedIn) {
            $headers['Logged-In'] = $loggedIn ? 'true' : 'false';
        }

        return $headers;
    }
}
