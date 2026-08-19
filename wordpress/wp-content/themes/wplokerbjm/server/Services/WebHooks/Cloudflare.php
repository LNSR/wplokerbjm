<?php

namespace WPLokerBJM\Services\WebHooks;

use WPLokerBJM\Configs\CredentialConfig;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Cloudflare cache purging via the purge_everything API.
 *
 * Any content change (post, meta, term) invalidates the entire zone.
 * Since the app is served through QUIC.cloud, Cloudflare just refetches
 * warm content on next request — full purges are cheap.
 *
 * Meta and term hooks self-unregister after the first fire within a
 * request to avoid duplicate zone-wide purges.
 * 
 * We rely on QUIC Cloud cache to simplify things. There is no need to
 * purge individual URLs or paths — a full-zone purge is cheap and fast.
 * @see \WPLokerBJM\Core\Container\Definitions\Factory
 * @phpstan-import-type CloudflareCred from CredentialConfig
 * @phpstan-type CFPurgeOptions array{
 *  purge_everything?: bool,
 *  hosts?: array<string>,
 *  prefixes?: array<string>,
 *  files?: array<string>
 * }
 */
class Cloudflare
{
    /**
     * @param CloudflareCred $credential filled by PHP-DI
     * @param WPHooksContainerRegistry $WPHooksContainerRegistry autowired by PHP-DI
     */
    public function __construct(
        private array $credential,
        private WPHooksContainerRegistry $WPHooksContainerRegistry,
    ) {
    }

    /**
     * Purge the entire Cloudflare zone cache.
     *
     * Registered on post-lifecycle hooks. NEVER self-unregisters — every
     * post in a batch operation must have its CDN cache purged individually.
     * @return bool
     */
    #[Action('save_post', 10, 2)]
    #[Action('deleted_post', 10, 1)]
    public function purgeAllCache()
    {
        return $this->sendPurgeRequest(['purge_everything' => true]);
    }

    /**
     * Purge the entire zone on the first meta change of the request.
     *
     * Self-unregisters after firing: subsequent meta hooks within the
     * same request are redundant (the zone was already purged).
     * @var static::class
     */
    #[Action('added_post_meta', 10, 4)]
    #[Action('updated_post_meta', 10, 4)]
    #[Action('deleted_post_meta', 10, 4)]
    public private(set) \Closure|false $purgeOnMetaChange { get => $this->purgeOnMetaChange ??= $this->createHandler(__PROPERTY__); }

    /**
     * Purge the entire zone on the first term change of the request.
     *
     * Self-unregisters after firing: subsequent term hooks within the
     * same request are redundant (the zone was already purged).
     * @var static::class
     */
    #[Action('created_term', 10, 0)]
    #[Action('edit_term', 10, 0)]
    #[Action('delete_term', 10, 0)]
    public private(set) \Closure|false $purgeOnTermChange { get => $this->purgeOnTermChange ??= $this->createHandler(__PROPERTY__); }

    /** 
     * @param string $propertyName string magic
     * @return \Closure|false
     */
    private function createHandler(string $propertyName): \Closure|false
    {
        if (empty(array_filter($this->credential))) {
            $this->WPHooksContainerRegistry->unregisterByClass(self::class);
            return false;
        }
        return function () use ($propertyName): bool {
            static $alreadyRun = false;
            if ($alreadyRun) return true;
            $alreadyRun = true;
            $this->WPHooksContainerRegistry->unregisterByCallable([$this, $propertyName]);
            return $this->purgeAllCache();
        };
    }

    /**
     * Send a purge request to the Cloudflare API.
     * @param CFPurgeOptions $payload
     *
     * @return bool
     */
    private function sendPurgeRequest(array $payload): bool
    {
        if (SharedUtils::isDevelopment()) {
            Logger::info('WebHook', 'Skipping Cloudflare purge in development environment.');
            return false;
        }

        $token = $this->credential['token'] ?? '';
        $zone = $this->credential['zone'] ?? '';

        if (!$token || !$zone) {
            Logger::warning('WebHook', 'Cloudflare credentials are missing.');
            return false;
        }

        $endpoint = sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', $zone);
        $body = wp_json_encode($payload);

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
            Logger::error('WebHook', 'Cloudflare purge error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            return true;
        }

        Logger::error('WebHook', 'Cloudflare purge failed ' . $code . ' body: ' . wp_remote_retrieve_body($response));
        return false;
    }
}