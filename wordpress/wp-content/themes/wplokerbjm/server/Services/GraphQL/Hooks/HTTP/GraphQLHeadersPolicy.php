<?php

namespace WPLokerBJM\Services\GraphQL\Hooks\HTTP;

use GraphQL\Executor\ExecutionResult;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter, Inject};
use WPLokerBJM\Core\Wordpress\Plugins\PluginList;
use WPLokerBJM\Shared\Utilities\{SharedUtils};
use WP_User;
use WPLokerBJM\Core\Wordpress\ContainerRegistryEvent;
use WPLokerBJM\Core\Wordpress\InstanceRuntimeRegistryEvent;
use WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\Integrations\LiteSpeedGraphQLIntegration;
use WPLokerBJM\Services\GraphQL\ETag\WPGraphQLETag;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * @phpstan-import-type GraphQLDataType from GraphQLRegistration
 */
final class GraphQLHeadersPolicy
{

    public function __construct(private LiteSpeedGraphQLIntegration $litespeedGraphqlIntegration, private WPGraphQLETag $eTag) {}
    private array $officialOrigins = [
        'https://dev.lokerbanjarmasin.my.id',
        'https://staging.lokerbanjarmasin.my.id',
        'https://lokerbanjarmasin.my.id',
        'https://wp.lokerbanjarmasin.my.id',
    ];

    /**
     * Restricts GraphQL CORS to same origin for security and adds X-WP-Nonce for logged-in users.
     */
    #[Filter(
        'graphql_response_headers_to_send',
        9,
        deferRegister: true,
        tag: ['graphql'],
        registerIf: static function (): bool {
            return !\is_admin();
        },
    )]
    public function ModifyHeaderGraphQL(array $headers): array
    {
        /**
         * remove WPGraphQL author hooks and inbuit core nocache Headers
         * @see \WPGraphQL\SmartCache\Cache\Results::init
         */
        \remove_all_filters('graphql_response_headers_to_send');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $this->allowedOrigins(), true)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
        }

        // Headers relevant to both preflight and actual responses.
        $headers['Access-Control-Allow-Credentials'] = 'true';
        $headers['Access-Control-Allow-Headers'] =
            ($headers['Access-Control-Allow-Headers'] ?? '') .
            ', X-WP-Nonce, If-None-Match, If-Match, Authorization';
        $headers['Access-Control-Max-Age'] = '360';

        $headers['Access-Control-Allow-Headers'] = $this->removeDuplicateValues($headers['Access-Control-Allow-Headers']);

        $headers['Vary'] = ($headers['Vary'] ?? '') . ', Origin, Authorization';
        $headers['Vary'] = $this->removeDuplicateValues($headers['Vary']);

        //! Preflight ends here.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            return $headers;
        }

        $headers = $this->eTag->setHeader($headers);

        $headers['Access-Control-Expose-Headers'] = 'X-WP-Nonce, ETag';

        $headers = $this->litespeedGraphqlIntegration->addLitespeedTagHeader($headers);

        unset($headers['Expires']);

        if (empty($headers['Last-Modified'])) {
            unset($headers['Last-Modified']);
        }

        $headers = $this->applyCachePolicy($headers);

        if (is_user_logged_in()) {
            $headers['Logged-In'] = 'true';
            $headers['X-WP-Nonce'] = \graphql_get_nonce();
            do_action(ContainerRegistryEvent::ACTIVATE_DEFERRED_BY_CALLABLE, [$this, 'disableGraphQLNocacheHeader']);
            do_action(ContainerRegistryEvent::ACTIVATE_DEFERRED_BY_CALLABLE, [$this, 'applyCachePolicy']);
        }
        return $headers;
    }
    #[Filter(
        'graphql_send_nocache_headers',
        9,
        deferRegister: true,
        registerIf: static function (): bool {
            return \is_user_logged_in() && !\is_admin();
        }
    )]
    public function disableGraphQLNocacheHeader(): bool
    {
        \remove_all_filters('graphql_send_nocache_headers');
        return false;
    }

    #[Filter(
        'nocache_headers',
        9,
        deferRegister: true,
        registerIf: static function (): bool {
            return \is_user_logged_in() && !\is_admin();
        }
    )]
    public function applyCachePolicy(array $headers): array
    {
        $loggedIn = is_user_logged_in();
        $isDev = SharedUtils::isDevelopment();
        $cacheValue = match (true) {
            $isDev => $loggedIn ? 'private, no-cache, must-revalidate' : 'public, no-cache, must-revalidate',
            default => $loggedIn ? 'private, no-cache, must-revalidate' : 'public, max-age=60, stale-while-revalidate=3600',
        };
        $headers['Cache-Control'] = $cacheValue;
        \remove_all_filters('nocache_headers');
        return $headers;
    }

    private function removeDuplicateValues(string $key): string
    {
        return $key
        |> (static fn($v) => explode(', ', $v))
        |> (static fn($v) => array_map('trim', $v))
        |> array_filter(...)
        |> array_unique(...)
        |> (static fn($v) => implode(', ', $v));
    }

    private function allowedOrigins(): array
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $origins = $this->officialOrigins;
        if (!SharedUtils::isDevelopment())
            return $origins;

        $allowed = [];
        $parts = wp_parse_url($origin);
        if (
            ($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') === 'localhost'
        ) {
            $allowed[] = $origin;
        }
        return array_merge($origins, $allowed);
    }
}
