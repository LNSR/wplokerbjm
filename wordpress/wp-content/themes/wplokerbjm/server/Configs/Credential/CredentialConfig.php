<?php

declare(strict_types=1);

namespace WPLokerBJM\Configs\Credential;

use RedisCredType;


/**
 * @phpstan-import-type RedisCredType from RedisCred
 * @phpstan-import-type R2CFCredType from R2CFCred
 * @phpstan-import-type CloudflareCredType from CloudflareCred
 */
class CredentialConfig
{
    /**
     * Return Redis connection credentials.
     *
     * @param ?RedisCredType $params.
     */
    public static function RedisCredential(?array $params = null): RedisCred
    {
        $params = [
            'host' => $params['host'] ?? (defined('WP_REDIS_HOST') ? WP_REDIS_HOST : null),
            'port' => $params['port'] ?? (defined('WP_REDIS_PORT') ? (int) WP_REDIS_PORT : null),
            'password' => $params['password'] ?? (defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : null),
            'database' => $params['database'] ?? (defined('WP_REDIS_DATABASE') ? (int) WP_REDIS_DATABASE : null),
            'sock' => $params['sock'] ?? (defined('WP_REDIS_SOCK') ? WP_REDIS_SOCK : null),
        ];
        return RedisCred::fromArray($params);
    }

    /**
     * Return Cloudflare R2 storage bucket credentials.
     *
     * @param ?R2CFCredType $params.
     */
    public static function R2CFCredential(?array $params = null): R2CFCred
    {
        $params = [
            'key' => $params['key'] ?? (defined('ADVMO_CLOUDFLARE_R2_KEY') ? ADVMO_CLOUDFLARE_R2_KEY : null),
            'secret' => $params['secret'] ?? (defined('ADVMO_CLOUDFLARE_R2_SECRET') ? ADVMO_CLOUDFLARE_R2_SECRET : null),
            'bucket' => $params['bucket'] ?? (defined('ADVMO_CLOUDFLARE_R2_BUCKET') ? ADVMO_CLOUDFLARE_R2_BUCKET : null),
            'domain' => $params['domain'] ?? (defined('ADVMO_CLOUDFLARE_R2_DOMAIN') ? ADVMO_CLOUDFLARE_R2_DOMAIN : null),
            'endpoint' => $params['endpoint'] ?? (defined('ADVMO_CLOUDFLARE_R2_ENDPOINT') ? ADVMO_CLOUDFLARE_R2_ENDPOINT : null),
        ];

        return R2CFCred::fromArray($params);
    }

    /**
     * Return Cloudflare API credentials (token + zone) for cache purging.
     *
     * This keeps the environment constants out of the service layer and
     * centralizes lookup logic for any future rotation or override needs.
     *
     * @param ?CloudflareCredType $params
     */
    public static function CloudflareCredential(?array $params = null): CloudflareCred
    {
        $params = [
            'token' => $params['token'] ?? (defined('WORDPRESS_API_TOKEN_DOMAIN') ? WORDPRESS_API_TOKEN_DOMAIN : null),
            'zone' => $params['zone'] ?? (defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : null),
        ];

        return CloudflareCred::fromArray($params);
    }
}
