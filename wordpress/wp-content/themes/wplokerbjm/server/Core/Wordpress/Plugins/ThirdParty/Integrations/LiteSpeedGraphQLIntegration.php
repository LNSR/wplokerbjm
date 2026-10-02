<?php

namespace WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\Integrations;

use WPLokerBJM\Core\Wordpress\Plugins\PluginConfigInterface;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Wordpress\Plugins\PluginList;

/**
 * LiteSpeed GraphQL Integration
 */
class LiteSpeedGraphQLIntegration implements PluginConfigInterface
{

    public static function isActive(): bool
    {
        return PluginList::LiteSpeed->isActive() && PluginList::WpGraphql->isActive();
    }

    /**
     * Call litespeed_purge when graphql_purge is called
     */
    #[Action('graphql_purge', deferRegister: true, tag: ['litespeed'])]
    private \Closure $litespeedPurgeCache = static function ($keys): void {
        do_action('litespeed_purge', $keys);
    };

    /**
     * Call wpgraphql_cache_purge_all when litespeed_purge_all is called
     */
    #[Action('litespeed_purge_all', deferRegister: true, tag: ['graphql'])]
    private \Closure $purgeAllGraphqlCache = static function (): void {
        do_action('wpgraphql_cache_purge_all');
    };

    /**
     * Set GraphQL Queries returned via HTTP GET|OPTIONS requests to be cacheable
     */
    public function setCacheable(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS')
            return;
        do_action('litespeed_control_force_cacheable');
        if (is_user_logged_in()) {
            do_action('litespeed_control_set_ttl', 3600);
            return;
        }
        do_action('litespeed_control_set_ttl', 86400);
    }
    public function addLitespeedTagHeader(array $headers = []): array
    {
        if (isset($headers['X-GraphQL-Keys'])) {
            do_action('litespeed_tag_add', explode(' ', $headers['X-GraphQL-Keys']));
            unset($headers['X-GraphQL-Keys']);
        }

        return $headers;
    }
}
