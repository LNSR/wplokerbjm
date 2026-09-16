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
            'host' => (string) ($params['host'] ?? (defined('WP_REDIS_HOST') ? (string) WP_REDIS_HOST : null)),
            'port' => (int) ($params['host'] ?? (defined('WP_REDIS_HOST') ? (int) WP_REDIS_HOST : null)),
            'password' => (string) ($params['password'] ?? (defined('WP_REDIS_PASSWORD') ? (string) WP_REDIS_PASSWORD : null)),
            'database' => (int) ($params['database'] ?? (defined('WP_REDIS_DATABASE') ? (int) WP_REDIS_DATABASE : null)),
            'sock' => (string) ($params['sock'] ?? (defined('WP_REDIS_SOCK') ? (string) WP_REDIS_SOCK : null)),
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
            'key' => (string) ($params['key'] ?? (defined('ADVMO_CLOUDFLARE_R2_KEY') ? ADVMO_CLOUDFLARE_R2_KEY : null)),
            'secret' => (string) ($params['secret'] ?? (defined('ADVMO_CLOUDFLARE_R2_SECRET') ? ADVMO_CLOUDFLARE_R2_SECRET : null)),
            'bucket' => (string) ($params['bucket'] ?? (defined('ADVMO_CLOUDFLARE_R2_BUCKET') ? ADVMO_CLOUDFLARE_R2_BUCKET : null)),
            'domain' => (string) ($params['domain'] ?? (defined('ADVMO_CLOUDFLARE_R2_DOMAIN') ? ADVMO_CLOUDFLARE_R2_DOMAIN : null)),
            'endpoint' => (string) ($params['endpoint'] ?? (defined('ADVMO_CLOUDFLARE_R2_ENDPOINT') ? ADVMO_CLOUDFLARE_R2_ENDPOINT : null)),
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
            'token' => (string) ($params['token'] ?? (defined('WORDPRESS_API_TOKEN_DOMAIN') ? WORDPRESS_API_TOKEN_DOMAIN : null)),
            'zone' => (string) ($params['zone'] ?? (defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : null)),
        ];

        return CloudflareCred::fromArray($params);
    }
}