<?php
namespace WPLokerBJM\Configs;

class CredentialConfig
{
   public static function SSGGitHubCredential(?array $params = null): array
   {
       return [
           'token' => $params['token'] ?? (defined('SSG_GITHUB_TOKEN') ? SSG_GITHUB_TOKEN : null),
           'owner' => $params['owner'] ?? (defined('SSG_GITHUB_OWNER') ? SSG_GITHUB_OWNER : null),
           'repo' => $params['repo'] ?? (defined('SSG_GITHUB_REPO') ? SSG_GITHUB_REPO : null),
           'workflow' => $params['workflow'] ?? (defined('SSG_GITHUB_WORKFLOW') ? SSG_GITHUB_WORKFLOW : null),
           'ref' => $params['ref'] ?? (defined('SSG_GITHUB_REF') ? SSG_GITHUB_REF : null),
       ];
   }

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
}