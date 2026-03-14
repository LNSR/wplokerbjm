<?php
namespace WPLokerBJM\Configs;

class CredentialConfig
{
   public static function RedisCredential(?array $params = null): array
   {
       return [
           'host' => $params['host'] ?? (defined('WP_REDIS_HOST') ? WP_REDIS_HOST : null),
           'port' => $params['port'] ?? (defined('WP_REDIS_PORT') ? WP_REDIS_PORT : null),
           'password' => $params['password'] ?? (defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : null),
           'database' => $params['database'] ?? (defined('WP_REDIS_DATABASE') ? WP_REDIS_DATABASE : null),
           'sock' => $params['sock'] ?? (defined('WP_REDIS_SOCK') ? WP_REDIS_SOCK : null),
       ];
   }
   public static function R2CFCredential(?array $params = null): array
   {
       return [
           'key' => $params['key'] ?? (defined('ADVMO_CLOUDFLARE_R2_KEY') ? ADVMO_CLOUDFLARE_R2_KEY : null),
           'secret' => $params['secret'] ?? (defined('ADVMO_CLOUDFLARE_R2_SECRET') ? ADVMO_CLOUDFLARE_R2_SECRET : null),
           'bucket' => $params['bucket'] ?? (defined('ADVMO_CLOUDFLARE_R2_BUCKET') ? ADVMO_CLOUDFLARE_R2_BUCKET : null),
           'domain' => $params['domain'] ?? (defined('ADVMO_CLOUDFLARE_R2_DOMAIN') ? ADVMO_CLOUDFLARE_R2_DOMAIN : null),
           'endpoint' => $params['endpoint'] ?? (defined('ADVMO_CLOUDFLARE_R2_ENDPOINT') ? ADVMO_CLOUDFLARE_R2_ENDPOINT : null),
       ];
   }

   /**
    * Return Cloudflare API credentials (token + zone) for cache purging.
    *
    * This keeps the environment constants out of the service layer and
    * centralizes lookup logic for any future rotation or override needs.
    *
    * @param array|null $params Optional overrides ('token' and/or 'zone').
    * @return array{token:?string,zone:?string}
    */
   public static function CloudflareCredential(?array $params = null): array
   {
       return [
           'token' => $params['token'] ?? (defined('WORDPRESS_API_TOKEN_DOMAIN') ? WORDPRESS_API_TOKEN_DOMAIN : null),
           'zone'  => $params['zone']  ?? (defined('CLOUDFLARE_ZONE_ID') ? CLOUDFLARE_ZONE_ID : null),
       ];
   }
}