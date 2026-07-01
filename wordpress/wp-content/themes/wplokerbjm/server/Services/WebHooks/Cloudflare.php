<?php

namespace WPLokerBJM\Services\WebHooks;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Cloudflare webhook helpers.
 *
 * Provides two purge strategies:
 *  - Targeted per-post purging (post/meta hooks) — uses get_permalink() to
 *    derive the canonical URL, then purges that path on both the WordPress
 *    frontend and the SvelteKit mirror, plus the home page.
 *  - Full-zone purging (term/taxonomy hooks) — clears everything.
 *
 * Credentials are resolved via CredentialConfig::CloudflareCredential().
 */
class Cloudflare
{
    private const WP_DOMAIN = 'wp.lokerbanjarmasin.my.id';
    private const APP_DOMAIN = 'lokerbanjarmasin.my.id';
    private const DEVICE_TYPES = ['desktop', 'mobile', 'tablet'];

    public function __construct(private array $credential) {

    }

    /**
     * Purge the home page and the affected post's permalink on both domains.
     *
     * Since "Cache by device type" is enabled, a single file-based purge
     * only invalidates one device variant.  We send three requests — one
     * per CF-Device-Type value (desktop, mobile, tablet) — so every
     * variant is cleared.
     *
     * Fires on post and meta hooks where a $post_id is available.
     *
     * @param mixed ...$args Hook arguments.
     * @return bool True on success, false on failure.
     */
    #[Action('save_post', 10, 2)]
    #[Action('deleted_post', 10, 1)]
    #[Action('added_post_meta', 10, 4)]
    #[Action('updated_post_meta', 10, 4)]
    #[Action('deleted_post_meta', 10, 4)]
    public function purgeCache(...$args): bool
    {
        if (SharedUtils::isDevelopment()) {
            Logger::info('WebHook', 'Skipping Cloudflare purge in development environment.');
            return false;
        }

        $postId = $this->extractPostId(current_action(), $args);
        $files = $this->buildPurgeUrls($postId);

        $success = true;

        foreach (self::DEVICE_TYPES as $deviceType) {
            if (!$this->sendPurgeRequest(['files' => $files], $deviceType)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Purge the entire zone cache.
     *
     * Fires on taxonomy / term hooks where no single post is relevant.
     *
     * @return bool True on success, false on failure.
     */
    #[Action('created_term', 10, 0)]
    #[Action('edit_term', 10, 0)]
    #[Action('delete_term', 10, 0)]
    public function purgeCacheAll(): bool
    {
        if (SharedUtils::isDevelopment()) {
            Logger::info('WebHook', 'Skipping Cloudflare purge in development environment.');
            return false;
        }

        return $this->sendPurgeRequest(['purge_everything' => true]);
    }

    /**
     * Extract the post ID from the current action's variadic arguments.
     *
     * @param string $action The current hook name (from current_action()).
     * @param array  $args   The variadic arguments passed to the handler.
     * @return int|null The post ID, or null if it cannot be determined.
     */
    private function extractPostId(string $action, array $args): ?int
    {
        return match ($action) {
            'save_post', 'deleted_post' => isset($args[0]) ? (int) $args[0] : null,
            'added_post_meta',
            'updated_post_meta',
            'deleted_post_meta' => isset($args[1]) ? (int) $args[1] : null,
            default => null,
        };
    }

    /**
     * Build the list of file URLs to purge.
     *
     * Always includes the SvelteKit home page.  When $postId is provided,
     * resolves the WordPress permalink and mirrors it on both domains,
     *
     * @param int|null $postId The post ID, or null for home-only.
     * @return string[] List of absolute URLs.
     */
    private function buildPurgeUrls(?int $postId): array
    {
        $urls = [
            sprintf('https://%s/', self::APP_DOMAIN),
        ];

        if ($postId === null) {
            return $urls;
        }

        $permalink = get_permalink($postId);

        if (!is_string($permalink) || $permalink === '') {
            return $urls;
        }

        $path = (string) wp_parse_url($permalink, PHP_URL_PATH);

        if ($path === '' || $path === '/') {
            return $urls;
        }

        $urls[] = sprintf('https://%s%s', self::WP_DOMAIN, $path);
        $urls[] = sprintf('https://%s%s', self::APP_DOMAIN, $path);

        return $urls;
    }

    /**
     * Send a purge request to the Cloudflare API.
     *
     * When $deviceType is set, the CF-Device-Type header is included so
     * that the request targets a specific device variant of the cached
     * resource (required when "Cache by device type" is enabled).
     *
     * @param array      $payload    The request body payload.
     * @param string|null $deviceType One of 'desktop', 'mobile', 'tablet', or null.
     * @return bool True on success, false on failure.
     */
    private function sendPurgeRequest(array $payload, ?string $deviceType = null): bool
    {
        $creds = $this->credential;
        $token = $creds['token'] ?? '';
        $zone = $creds['zone'] ?? '';

        if (!$token || !$zone) {
            Logger::warning('WebHook', 'Cloudflare credentials are missing.');
            return false;
        }

        $endpoint = sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', $zone);
        $body = wp_json_encode($payload);

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ];

        if ($deviceType !== null) {
            $headers['CF-Device-Type'] = $deviceType;
        }

        $response = wp_remote_request($endpoint, [
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            Logger::error('WebHook', 'Cloudflare purge error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = wp_remote_retrieve_body($response);

        if ($code === 200) {
            return true;
        }

        Logger::error('WebHook', 'Cloudflare purge failed ' . $code . ' body: ' . $data);
        return false;
    }
}
