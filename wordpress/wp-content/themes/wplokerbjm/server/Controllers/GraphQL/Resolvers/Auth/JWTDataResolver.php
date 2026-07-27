<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers\Auth;

use JWTDataShape;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use DI\Attribute\Injectable;

/**
 * @phpstan-type JWTDataShape array{token?: string, username?: string, password?: string}
 */
#[Injectable(lazy: true)]
class JWTDataResolver
{

    const REST_NAMESPACE = '/jwt-auth/v1/';

    /**
     * Proxy the WP JWT REST endpoints into GraphQL.
     *
     * The caller may provide either a username/password pair (to request a token)
     * or an existing token (to validate it). Returns 'ok' on success or null on failure.
     * On successful login, sets an HTTP-only SameSite=Lax cookie with the JWT.
     *
     * @param mixed $root The root Mutation object (unused)
     * @param JWTDataShape $args Mutation inputs
     * @return string|null 'ok' on success, null on failure
     */
    public function resolveJWTorValidate($root, array $args): ?string
    {
        // $args already contains our named inputs from the GraphQL field.
        $argArr = is_array($args) ? $args : [];
        $token = $argArr['token'] ?? null;
        $username = $argArr['username'] ?? null;
        $password = $argArr['password'] ?? null;
        try {
            // login if credentials present, set HTTP-only cookie on success
            if (!empty($username) && !empty($password)) {
                return $this->validateCredentialWP($username, $password);
            }
            // if only token provided we validate, no cookie change on validation
            if (!empty($token)) {
                return $this->validateOnlyToken($token);
            }
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'ThemeDataResolver::resolveJWTorValidate error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Validate a JWT token against the WP REST endpoint.
     *
     * Sends a POST request to the JWT validate endpoint with the token
     * as a Bearer Authorization header.
     *
     * @phpstan-param JWTDataShape['token'] $token JWT token string to validate
     * @return string|null 'ok' if valid, null on failure
     */
    private function validateOnlyToken(string $token): string|null
    {
        $req = new \WP_REST_Request('POST', self::REST_NAMESPACE . 'token/validate');
        $req->set_header('Content-Type', 'application/json');
        $req->set_header('Authorization', 'Bearer ' . $token);
        $resp = rest_do_request($req);
        if (is_wp_error($resp)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::error('GraphQL', 'JWT validate wp_error: ' . $resp->get_error_message());
            }
            return null;
        }
        $code = method_exists($resp, 'get_status') ? $resp->get_status() : 0;
        $data = method_exists($resp, 'get_data') ? $resp->get_data() : null;
        if ($code === 200 && !empty($data['data']['status']) && $data['data']['status'] === 200) {
            return 'ok';
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            Logger::error('GraphQL', "JWT validate failed with code={$code} data=" . print_r($data, true));
        }
        return null;
    }

    /**
     * Authenticate user credentials against WordPress and obtain a JWT.
     *
     * Sends a POST request to the JWT token endpoint with username/password.
     * On success, automatically sets an HTTP-only cookie with the token.
     *
     * @phpstan-param JWTDataShape['username'] $username WordPress username
     * @phpstan-param JWTDataShape['password'] $password WordPress password
     * @return string|null 'ok' on successful authentication, null on failure
     */
    private function validateCredentialWP(string $username, string $password): string|null
    {
        $req = new \WP_REST_Request('POST', self::REST_NAMESPACE . 'token');
        $req->set_header('Content-Type', 'application/json');
        $req->set_body_params([
            'username' => $username,
            'password' => $password,
        ]);
        $resp = rest_do_request($req);
        if (is_wp_error($resp)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::error('GraphQL', 'JWT login wp_error: ' . $resp->get_error_message());
            }
            return null;
        }
        $code = method_exists($resp, 'get_status') ? $resp->get_status() : 0;
        /**
         * @var JWTDataShape $data
         */
        $data = method_exists($resp, 'get_data') ? $resp->get_data() : null;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            Logger::error('GraphQL', "JWT login response code={$code} data=" . print_r($data, true));
        }
        $token = $data['token'] ?? null;
        if ($token === null) {
            return null;
        }
        $this->setJwtCookie($token);
        return 'ok';
    }

    /**
     * Set an HTTP-only SameSite=Lax cookie containing the JWT token.
     * Cookie name matches the frontend constant: cookieJwtName = "jwt-token".
     *
     * The cookie domain is derived from the headless frontend URL via
     * SharedUtils::headlessDomainRedirect().
     *
     * @param JWTDataShape['token'] $token JWT token string to store in cookie
     */
    private function setJwtCookie(string $token): void
    {
        $domain = static function () {
            $url = SharedUtils::headlessDomainRedirect();
            $parsed_url = (string) parse_url($url, PHP_URL_HOST) ?: $url;
            $striped_host = preg_replace('/:\\d+$/', '', $parsed_url); // strip port
            $trimmed = trim($striped_host, '/'); // trim root
            return '.' . $trimmed; // add root prefix for cookie
        };

        setcookie('jwt-token', $token, [
            'domain' => $domain(),
            'expires' => time() + (7 * 60 * 60 * 24), // 7 day
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => is_ssl(),
            'samesite' => 'Lax',
        ]);
    }
}