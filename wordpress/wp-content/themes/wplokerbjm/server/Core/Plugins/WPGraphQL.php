<?php

namespace WPLokerBJM\Core\Plugins;

use DI\Attribute\Injectable;
use GraphQLDataType;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\Support\WPHooksRegistry;
use GraphQL\Executor\ExecutionResult;
use WPGraphQL\Router;
use WP_User;

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

    public function __construct(
        private WPHooksRegistry $hookRegistry,
        private LiteSpeedGraphQLIntegration $litespeedGraphQLIntegration,
        private WPGraphQLETag $eTag
    ) {
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
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (empty($origin))
            return $requireNonce;

        $result = \in_array($origin, $this->allowedOrigins(), true);
        return $result ? false : $requireNonce;
    }

    /**
     * Unified init request handler: checks ETag cache before performing auth.
     */
    #[Action('init_graphql_request', 9)]
    public function handleInitRequest(): void
    {
        $this->eTag->checkEarly304();
        $this->authenticateViaCookie();
    }

    /**
     * Authenticate GraphQL requests
     * Must be logged in Wordpress to have the cookies, but this allows GraphQL requests to be authenticated for decoupled frontend.
     */
    private function authenticateViaCookie(): void
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

        if (!SharedUtils::isDevelopment())
            return $officalOrigins;

        $allowed = [];
        $parts = wp_parse_url($origin);
        if (
            ($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') === 'localhost'
        ) {
            $allowed[] = $origin;
        }
        return array_merge($officalOrigins, $allowed);
    }
    #[Filter('graphql_send_nocache_headers')]
    public function overrideApplyCachePolicy(): bool
    {
        return false;
    }

    /**
     * Restricts GraphQL CORS to same origin for security and adds X-WP-Nonce for logged-in users.
     */
    #[Filter('graphql_response_headers_to_send', 11)]
    public function ModifyHeaderGraphQL(array $headers): array
    {

        $headers = $this->eTag->setHeader($headers);

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

        return $headers = $this->applyCachePolicy($headers);
    }

    private function applyCachePolicy(array $headers): array
    {
        $loggedIn = is_user_logged_in();
        $cacheValue = $loggedIn
            ? 'private, max-age=60, must-revalidate'
            : 'public, max-age=360, stale-while-revalidate=86400';

        $headers['Cache-Control'] = $cacheValue;
        $this->removeGraphQLAuthorHooks();
        return $headers;
    }

    /**
     * Remove hardcoded author hooks
     * * Some are being set as PHP_INT_MAX for crying out loud
     */
    private function removeGraphQLAuthorHooks()
    {
        global $wp_filter;

        // Direct removal from $wp_filter bypassing instance hash requirement
        if (isset($wp_filter['graphql_response_headers_to_send']->callbacks[PHP_INT_MAX])) {
            unset($wp_filter['graphql_response_headers_to_send']->callbacks[PHP_INT_MAX]);
        }
    }

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
        $http_status_code = $this->checkJwt401Impl($http_status_code, $graphql_response);
        $this->eTag->computeAndStore($graphql_response, $query, $operation_name, (array) $variables, $user);
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
            return SharedUtils::isDevelopment() ? 'on' : 'off';
        }
        return $value;
    }

    /**
     * Check JWT 401 implementation.
     * ? Candidate to move to another concern later
     * @param int $http_status_code
     * @param ExecutionResult $graphql_response
     * @return int
     */
    private function checkJwt401Impl(
        int $http_status_code,
        ExecutionResult $graphql_response,
    ): int {
        /**
         * @var GraphQLDataType $data
         */
        $data = $graphql_response->data ?? null;
        if (array_key_exists('jwt', $data) && !$data['jwt']) {
            return 401;
        }
        return $http_status_code;
    }
}


/**
 * @phpstan-import-type GraphQLDataType from \WPLokerBJM\Services\GraphQL\GraphQLRegistration
 */
class WPGraphQLETag
{
    /** @var string The current request ETag, computed from response data. */
    private string $etag = '' {
        set(string $etag) {
            $this->etag = trim($etag);
        }
    }

    /**
     * Fields whose responses depend on user identity/session and must never be ETag-cached.
     * Two users with the same query+variables get different results for these fields.
     * @var list<key-of<GraphQLDataType>>
     */
    private const array SKIP_ETAG_FIELDS = ['jwt', 'syncBookmark'];

    /**
     * Intercept early in the request cycle to return a 304 if the incoming If-None-Match matches our cached ETag.
     * Uses Router::get_raw_data() — the Router hasn't run yet at this point, so we parse the raw body ourselves.
     */
    public function checkEarly304(): void
    {
        $ifNoneMatch = trim(stripslashes($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($ifNoneMatch === '') {
            return;
        }

        $cachedValue = Cache::get(CacheKey::GRAPHQL_ETAG_PREFIX . $this->buildRequestHash());

        // Redis somehow wraps values in ['data' => $value]
        $cachedEtag = match (true) {
            is_string($cachedValue) => trim($cachedValue),
            is_array($cachedValue) && isset($cachedValue['data']) => trim((string) $cachedValue['data']),
            default => '',
        };

        if ($cachedEtag !== '' && $cachedEtag === $ifNoneMatch) {
            status_header(304);
            exit;
        }
    }

    /**
     * Compute and store ETag if the GraphQL execution returned valid data.
     */
    public function computeAndStore(ExecutionResult $graphqlResponse, string $_query = '', string $_operationName = '', array $_variables = [], ?WP_User $user = null): void
    {
        /**
         * @var GraphQLDataType $data
         */
        $data = $graphqlResponse->data;
        if (empty($data)) {
            return;
        }

        if ($this->shouldSkipEtag($data)) {
            return;
        }

        $userId = $user?->ID ?? get_current_user_id();
        $etag = 'W/"' . hash('xxh128', serialize($data) . ':' . $userId) . '"';
        $this->etag = $etag;

        Cache::set(
            CacheKey::GRAPHQL_ETAG_PREFIX . $this->buildRequestHash(),
            $etag,
            86400,
        );
    }

    /**
     * Inject ETag header into response headers array.
     */
    public function setHeader(array $headers): array
    {
        if ($this->etag !== '') {
            $headers['ETag'] = $this->etag;
        }
        return $headers;
    }

    /**
     * Build a deterministic cache key from the raw request.
     * Reads from $_REQUEST — same source before AND after Router execution,
     * so checkEarly304() and computeAndStore() always produce the same key.
     *
     * For persisted queries, the sha256Hash in extensions uniquely identifies
     * the query document (the raw query string is empty for GET requests).
     */
    private function buildRequestHash(): string
    {
        static $hash = null;

        if ($hash === null) {
            $query = $_REQUEST['query'] ?? '';
            $operationName = $_REQUEST['operationName'] ?? '';
            $extensions = $_REQUEST['extensions'] ?? '';

            $rawVars = $_REQUEST['variables'] ?? '';
            if (is_string($rawVars) && $rawVars !== '') {
                $variables = json_decode($rawVars, true) ?: [];
            } else {
                $variables = is_array($rawVars) ? $rawVars : [];
            }

            $authFingerprint = $this->buildAuthFingerprint();
            $hash = hash('xxh128', serialize(compact('query', 'variables', 'operationName', 'extensions', 'authFingerprint')));
        }

        return $hash;
    }

    /**
     * @param GraphQLDataType $data
     */
    private function shouldSkipEtag(array $data): bool
    {
        foreach (self::SKIP_ETAG_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build a fingerprint from authentication cookies so ETags are unique per user.
     * Returns empty string for unauthenticated requests (no user-specific caching).
     */
    private function buildAuthFingerprint(): string
    {
        $authCookies = [];
        foreach ($_COOKIE as $name => $value) {
            if (
                str_starts_with($name, 'wordpress_logged_in_') ||
                str_starts_with($name, 'wordpress_sec_') ||
                $name === 'jwt-token'
            ) {
                $authCookies[$name] = $value;
            }
        }
        if (empty($authCookies)) {
            return '';
        }
        return hash('xxh128', serialize($authCookies));
    }
}