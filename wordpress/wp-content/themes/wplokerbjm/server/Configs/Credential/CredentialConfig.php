<?php

namespace WPLokerBJM\Configs\Credential;


/**
 * @phpstan-type RedisCred array{
 *     host: ?string,
 *     port: ?int,
 *     password: ?string,
 *     database: ?int,
 *     sock: ?string
 * }
 * @phpstan-type R2CFCred array{
 *     key: ?string,
 *     secret: ?string,
 *     bucket: ?string,
 *     domain: ?string,
 *     endpoint: ?string
 * }
 * @phpstan-type CloudflareCred array{
 *     token: ?string,
 *     zone: ?string
 * }
 */
class CredentialConfig
{
    /**
     * Return Redis connection credentials.
     *
     * @param ?RedisCred $params.
     * @return RedisCred
     */
    public static function RedisCredential(?array $params = null): array
    {
        $params['host'] ??= (string) ($params['host'] ?? (defined('WP_REDIS_HOST') ? (string) WP_REDIS_HOST : null));
        $params['port'] ??= (int) ($params['port'] ?? (defined('WP_REDIS_PORT') ? (int) WP_REDIS_PORT : null));
        $params['password'] ??= (string) ($params['password'] ?? (defined('WP_REDIS_PASSWORD') ? (string) WP_REDIS_PASSWORD : null));
        $params['database'] ??= (int) ($params['database'] ?? (defined('WP_REDIS_DATABASE') ? (int) WP_REDIS_DATABASE : null));
        $params['sock'] ??= (string) ($params['sock'] ?? (defined('WP_REDIS_SOCK') ? (string) WP_REDIS_SOCK : null));

        return $params;
    }

    /**
     * Return Cloudflare R2 storage bucket credentials.
     *
     * @param ?R2CFCred $params.
     * @return R2CFCred
     */
    public static function R2CFCredential(?array $params = null): array
    {
        $params['key'] ??= (string) ($params['key'] ?? (defined('ADVMO_CLOUDFLARE_R2_KEY') ? ADVMO_CLOUDFLARE_R2_KEY : null));
        $params['secret'] ??= (string) ($params['secret'] ?? (defined('ADVMO_CLOUDFLARE_R2_SECRET') ? ADVMO_CLOUDFLARE_R2_SECRET : null));
        $params['bucket'] ??= (string) ($params['bucket'] ?? (defined('ADVMO_CLOUDFLARE_R2_BUCKET') ? ADVMO_CLOUDFLARE_R2_BUCKET : null));
        $params['domain'] ??= (string) ($params['domain'] ?? (defined('ADVMO_CLOUDFLARE_R2_DOMAIN') ? ADVMO_CLOUDFLARE_R2_DOMAIN : null));
        $params['endpoint'] ??= (string) ($params['endpoint'] ?? (defined('ADVMO_CLOUDFLARE_R2_ENDPOINT') ? ADVMO_CLOUDFLARE_R2_ENDPOINT : null));

        return $params;
    }

    /**
     * Return Cloudflare API credentials (token + zone) for cache purging.
     *
     * This keeps the environment constants out of the service layer and
     * centralizes lookup logic for any future rotation or override needs.
     *
     * @param ?CloudflareCred $params
     * @return CloudflareCred
     */
    public static function CloudflareCredential(?array $params = null): array
    {
        $params['token'] ??= (string) ($params['token'] ?? (defined('WORDPRESS_API_TOKEN_DOMAIN') ? WORDPRESS_API_TOKEN_DOMAIN : null));
        $params['zone']  ??= (string) ($params['zone']  ?? (defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : null));

        return $params;
    }
}