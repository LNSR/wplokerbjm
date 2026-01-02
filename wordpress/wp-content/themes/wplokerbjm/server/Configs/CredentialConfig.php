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
}