<?php
namespace WPLokerBJM\Core\Plugins\ThirdParty\WPGraphQL\Services;

use GraphQL\Executor\ExecutionResult;
use WPLokerBJM\Services\GraphQL\GraphQLRegistration;
use WPLokerBJM\Shared\Cache\{CacheKey, Cache};

/**
 * @phpstan-import-type GraphQLDataType from GraphQLRegistration
 */
class WPGraphQLETag
{
    /** @var string The current request ETag, computed from response data. */
    public private(set) string $etag = '' {
        set(string $etag) {
            $this->etag = trim($etag);
        }
    }
    public private(set) string $hash = '';

    /**
     * Fields whose responses depend on user identity/session and must never be ETag-cached.
     * Two users with the same query+variables get different results for these fields.
     * @var key-of<GraphQLDataType>
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

        // Redis somehow wraps values in ['data' => $value] and php serializer cause extra characters
        $cachedEtag = match (true) {
            is_string($cachedValue) => trim($cachedValue),
            is_array($cachedValue) && isset($cachedValue['data']) => trim((string) $cachedValue['data']),
            default => '',
        };

        if ($cachedEtag !== '' && $cachedEtag === $ifNoneMatch) {
            status_header(304);
            header('ETag: ' . $cachedEtag);
            exit;
        }
    }

    /**
     * Compute and store ETag if the GraphQL execution returned valid data.
     */
    public function computeAndStore(ExecutionResult $graphqlResponse, string $_query = '', string $_operationName = '', array $_variables = [], ?\WP_User $user = null): void
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
        if ($this->hash !== '')
            return $this->hash;

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
        $this->hash = hash('xxh128', serialize(compact('query', 'variables', 'operationName', 'extensions', 'authFingerprint')));

        return $this->hash;
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