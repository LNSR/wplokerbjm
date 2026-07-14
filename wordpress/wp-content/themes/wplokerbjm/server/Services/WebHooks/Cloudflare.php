<?php

namespace WPLokerBJM\Services\WebHooks;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooksRegistry;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Log\Logger;
use DI\Attribute\Injectable;

/**
 * Cloudflare webhook helpers.
 *
 * Provides two purge strategies:
 *  - Prefix-based per-post purging (post/meta hooks) — uses get_permalink()
 *    to derive the canonical URL path, then purges that path plus the home
 *    page as Cloudflare URL prefixes.
 *  - Full-zone purging (term/taxonomy hooks) — clears everything.
 *
 * Credentials are injected via the constructor using PHP-DI.
 * @see \WPLokerBJM\Core\Container\Definitions\Factory
 * 
 * @phpstan-import-type CloudflareCred from \WPLokerBJM\Configs\CredentialConfig
 */
#[Injectable(lazy: true)]
class Cloudflare
{
    private const APP_DOMAIN = 'lokerbanjarmasin.my.id';

    /**
     * @param CloudflareCred $credential filled by PHP-DI
     * @param WPHooksRegistry $wpHooksRegistry autowired by PHP-DI
     */
    public function __construct(private array $credential, private WPHooksRegistry $wpHooksRegistry)
    {

    }

    /**
     * Purge the home page and the affected post's permalink on the app domain.
     *
     * Uses Cloudflare's prefix-based cache purge so all URL variants
     * (query parameters, subpaths) are invalidated in a single call.
     *
     * Registered on post-lifecycle hooks.  NEVER self-unregisters — every
     * post in a batch operation must have its CDN cache purged individually.
     *
     * @param mixed ...$args Hook arguments.
     * @return bool True on success, false on failure.
     */
    #[Action('save_post', 10, 2)]
    #[Action('deleted_post', 10, 1)]
    public function purgeCache(...$args): bool
    {
        $postId = $this->extractPostId(current_action(), $args);
        $prefixes = $this->buildPurgePrefixes($postId);

        return $this->sendPurgeRequest(['prefixes' => $prefixes]);
    }

    /**
     * Purge triggered by meta changes — self-unregisters after first fire.
     *
     * Meta hooks (added_post_meta, updated_post_meta, deleted_post_meta)
     * are redundant within a single post lifecycle: by the time these fire,
     * `save_post` has already triggered a purge.  After the first meta hook
     * fires, this handler removes itself for the rest of the request.
     *
     * In a batch operation this means post #1's meta change triggers a purge
     * (and unregisters), while posts #2–50 rely on their `save_post` /
     * `deleted_post` hooks (which remain registered on a different method).
     *
     * @param mixed ...$args Hook arguments.
     * @return bool True on success, false on failure.
     */
    #[Action('added_post_meta', 10, 4)]
    #[Action('updated_post_meta', 10, 4)]
    #[Action('deleted_post_meta', 10, 4)]
    public function purgeOnMetaChange(...$args): bool
    {
        $this->wpHooksRegistry->unregisterByMethod(self::class, __FUNCTION__);

        return $this->purgeCache(...$args);
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
        $this->wpHooksRegistry->unregisterByMethod(self::class, __FUNCTION__);

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
     * Build the list of URL prefixes to purge.
     *
     * Always includes the SvelteKit home page.  When $postId is provided,
     * resolves the WordPress permalink and mirrors its path on the app domain.
     *
     * @param int|null $postId The post ID, or null for home-only.
     * @return string[] List of absolute URL prefixes.
     */
    private function buildPurgePrefixes(?int $postId): array
    {
        $prefixes = [
            sprintf('https://%s/', self::APP_DOMAIN),
        ];

        if ($postId === null) {
            return $prefixes;
        }

        $permalink = get_permalink($postId);

        if (!is_string($permalink) || $permalink === '') {
            return $prefixes;
        }

        $path = (string) wp_parse_url($permalink, PHP_URL_PATH);

        if ($path === '' || $path === '/') {
            return $prefixes;
        }

        $prefixes[] = sprintf('https://%s%s', self::APP_DOMAIN, $path);

        return $prefixes;
    }

    /**
     * Send a purge request to the Cloudflare API.
     *
     * @param array $payload The request body payload.
     * @return bool True on success, false on failure.
     */
    private function sendPurgeRequest(array $payload): bool
    {
        if (SharedUtils::isDevelopment()) {
            Logger::info('WebHook', 'Skipping Cloudflare purge in development environment.');
            return false;
        }

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
