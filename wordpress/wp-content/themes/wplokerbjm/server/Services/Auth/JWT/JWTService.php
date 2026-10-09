<?php

namespace WPLokerBJM\Services\Auth\JWT;

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;


/**
 * @phpstan-type JWTDataShape array{token?: string, username?: string, password?: string}
 * 
 * Source types below: {@link https://wordpress.org/plugins/jwt-authentication-for-wp-rest-api/}
 * @phpstan-type JWTResponseRESTSuccess array{
 *  token: string,
 *  user_display_name: string,
 *  user_email: string,
 *  user_nicename: string
 * }
 * @phpstan-type JWTResponseRESTError array{
 * code: string,
 * data: array{
 *   status: int
 * },
 * message: string
 * }
 */
class JWTService
{

    public const REST_NAMESPACE = '/jwt-auth/v1/';

    /**
     * Validate a JWT token against the WP REST endpoint.
     *
     * Sends a POST request to the JWT validate endpoint with the token
     * as a Bearer Authorization header.
     *
     * @phpstan-param JWTDataShape['token'] $token JWT token string to validate
     * @return string|null 'ok' if valid, null on failure
     */
    public function validateOnlyToken(string $token): string|null
    {
        $req = new \WP_REST_Request('POST', self::REST_NAMESPACE . 'token/validate');
        $req->set_header('Content-Type', 'application/json');
        $req->set_header('Authorization', 'Bearer ' . $token);
        $resp = rest_do_request($req);
        if (is_wp_error($resp)) {
            SharedUtils::isDevelopment() && Logger::error(__CLASS__, 'JWT validate wp_error: ' . $resp->get_error_message());
            return null;
        }
        $code = $resp->get_status() ?: 0;
        /**
         * @var JWTResponseRESTError $data
         */
        $data = $resp->get_data() ?: null;
        if ($code === 200 && !empty($data['data']['status']) && $data['data']['status'] === 200) {
            return 'ok';
        }
        SharedUtils::isDevelopment() && Logger::error(__CLASS__, "JWT validate failed with code={$code} data=" . print_r($data, true));
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
     * @return 'ok'|null on successful authentication, null on failure
     */
    public function validateCredentialWP(string $username, string $password): string|null
    {
        $req = new \WP_REST_Request('POST', self::REST_NAMESPACE . 'token');
        $req->set_header('Content-Type', 'application/json');
        $req->set_body_params([
            'username' => $username,
            'password' => $password,
        ]);
        $resp = rest_do_request($req);
        if (is_wp_error($resp)) {
            SharedUtils::isDevelopment() && Logger::error(__CLASS__, 'JWT login wp_error: ' . $resp->get_error_message());
            return null;
        }
        $code = $resp->get_status() ?: 0;
        $data = $resp->get_data() ?: null;
        SharedUtils::isDevelopment() && Logger::error(__CLASS__, "JWT login response code={$code} data=" . print_r($data, true));
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
     * @param string $token JWT token string to store in cookie
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
            'secure' => SharedUtils::isDevelopment() ? \is_ssl() : true,
            'httponly' => SharedUtils::isDevelopment() ? \is_ssl() : true,
            'samesite' => 'Lax',
        ]);
    }
}
