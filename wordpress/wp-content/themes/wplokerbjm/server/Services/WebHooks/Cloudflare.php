<?php

namespace WPLokerBJM\Services\WebHooks;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Configs\CredentialConfig;

/**
 * Cloudflare webhook helpers.
 *
 * Currently this class exposes a full-zone cache purge method and an
 * attribute-driven handler which fires on various post/taxonomy/meta hooks.
 * The purge uses a bearer-token API key stored in the WORDPRESS_API_TOKEN_DOMAIN
 * constant.
 */
class Cloudflare
{
    /**
     * Purge the entire cache for the configured zone.
     *
     * @return bool True on success, false on failure.
     */
    #[Action('save_post', 10, 2)]
    #[Action('deleted_post', 10, 1)]
    #[Action('created_term', 10, 0)]
    #[Action('edit_term', 10, 0)]
    #[Action('delete_term', 10, 0)]
    #[Action('added_post_meta', 10, 4)]
    #[Action('updated_post_meta', 10, 4)]
    #[Action('deleted_post_meta', 10, 4)]
    public static function purgeCache(...$args): bool
    {
        $creds = CredentialConfig::CloudflareCredential();
        $token = $creds['token'] ?? '';
        $zone  = $creds['zone']  ?? '';

        if (! $token || ! $zone) {
            return false;
        }

        $endpoint = sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', $zone);
        $body = wp_json_encode(['purge_everything' => true]);

        $response = wp_remote_request($endpoint, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            error_log('Cloudflare purge error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = wp_remote_retrieve_body($response);

        if ($code === 200) {
            return true;
        }

        error_log('Cloudflare purge failed ' . $code . ' body: ' . $data);
        return false;
    }
}
