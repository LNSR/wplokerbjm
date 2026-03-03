<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;

class ThemeDataResolver
{
    public function __construct(
        private readonly \WPLokerBJM\Services\GraphQL\GraphQLData $graphqlData,
    ) {
    }
    public function resolveThemeData(): array
    {
        try {
            $themeData = $this->graphqlData->getThemeData(); // cached internally
            $result = [
                'data' => $themeData,
            ];

            return $result;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'ThemeDataResolver::resolveThemeData error: ' . $e->getMessage());
            return [
                'data' => null,
            ];
        }
    }
    /**
     * Proxy the WP JWT REST endpoints into GraphQL.  The caller may provide
     * either a username/password pair (to request a token) or an existing token
     * (to validate it).  Returns the token string on success or null on failure.
     *
     * @param mixed ...$args Root query arguments; we only care about the second
     *                        argument which is the associative array of inputs.
     */
    public function resolveJWTorValidate($root, $args): ?string
    {
        // $args already contains our named inputs from the GraphQL field.
        $argArr = is_array($args) ? $args : [];
        try {
            // if token provided we validate (no cookie change on validation)
            if (!empty($argArr['token'])) {
                if (function_exists('rest_do_request')) {
                    $req = new \WP_REST_Request('POST', '/jwt-auth/v1/token/validate');
                    $req->set_header('Content-Type', 'application/json');
                    $req->set_body_params(['token' => $argArr['token']]);
                    $resp = rest_do_request($req);
                    if (is_wp_error($resp)) {
                        return null;
                    }
                    $code = method_exists($resp, 'get_status') ? $resp->get_status() : 0;
                    $data = method_exists($resp, 'get_data') ? $resp->get_data() : null;
                    if ($code === 200 && !empty($data['data']['status']) && $data['data']['status'] === 200) {
                        return 'ok';
                    }
                    return null;
                }
                // fallback to remote post if rest_do_request unavailable
                $response = wp_remote_post(
                    site_url('/wp-json/jwt-auth/v1/token/validate'),
                    [
                        'body' => json_encode(['token' => $argArr['token']]),
                        'headers' => ['Content-Type' => 'application/json'],
                        'timeout' => 5,
                    ]
                );

                if (is_wp_error($response)) {
                    return null;
                }
                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) {
                    return null;
                }
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (!empty($data['data']['status']) && $data['data']['status'] === 200) {
                    return 'ok';
                }
                return null;
            }

            // otherwise attempt login if credentials present, set HTTP-only cookie on success
            if (!empty($argArr['username']) && !empty($argArr['password'])) {
                if (function_exists('rest_do_request')) {
                    $req = new \WP_REST_Request('POST', '/jwt-auth/v1/token');
                    $req->set_header('Content-Type', 'application/json');
                    $req->set_body_params([
                        'username' => $argArr['username'],
                        'password' => $argArr['password'],
                    ]);
                    $resp = rest_do_request($req);
                    if (is_wp_error($resp)) {
                        Logger::error('GraphQL', 'JWT login wp_error: ' . $resp->get_error_message());
                        return null;
                    }
                    $code = method_exists($resp, 'get_status') ? $resp->get_status() : 0;
                    $data = method_exists($resp, 'get_data') ? $resp->get_data() : null;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        Logger::error('GraphQL', "JWT login response code={$code} data=" . print_r($data, true));
                    }
                    $token = $data['token'] ?? null;
                    if ($token === null) {
                        return null;
                    }
                    self::setJwtCookie($token);
                    return $token;
                }
                // fallback to remote post
                $response = wp_remote_post(
                    site_url('/wp-json/jwt-auth/v1/token'),
                    [
                        'body' => json_encode([
                            'username' => $argArr['username'],
                            'password' => $argArr['password'],
                        ]),
                        'headers' => ['Content-Type' => 'application/json'],
                        'timeout' => 5,
                    ]
                );

                if (is_wp_error($response)) {
                    Logger::error('GraphQL', 'JWT login wp_error: ' . $response->get_error_message());
                    return null;
                }
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    Logger::error('GraphQL', "JWT login response code={$code} body=" . substr($body, 0, 500));
                }
                if ($code !== 200) {
                    return null;
                }
                $data = json_decode($body, true);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    Logger::error('GraphQL', 'JWT login parsed=' . print_r($data, true));
                }
                $token = $data['token'] ?? null;
                if ($token === null) {
                    return null;
                }
                self::setJwtCookie($token);
                return $token;
            }
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'ThemeDataResolver::resolveJWTorValidate error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Set an HTTP-only SameSite=Lax cookie containing the JWT token.
     * Cookie name matches the frontend constant: cookieJwtName = "jwt-token".
     */
    private static function setJwtCookie(string $token): void
    {
        $url = SharedUtils::headlessDomainRedirect();
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $host = preg_replace('/:\\d+$/', '', $host);
        $host = trim($host, '/');
        $domain = '.' . $host; // ensure cookie works across subdomains

        setcookie('jwt-token', $token, [
            'domain' => $domain,
            'expires' => time() + 86400, // 1 day
            'path' => '/',
            'secure' => true,    // required for SameSite=None
            'httponly' => is_ssl(),
            'samesite' => 'Lax',
        ]);
    }
}