<?php
namespace WPLokerBJM\Core\Plugins;
use \DI\Attribute\Injectable;
use WPLokerBJM\Core\Container\Attributes\Filter;
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};
use WPLokerBJM\Core\Container\Support\WPHooksRegistry;

/**
 * JWT Auth Hooks
 * @link https://github.com/Tmeister/wp-api-jwt-auth
 */
#[Injectable(lazy: true)]
class WPRestJWTHooks
{

    public function __construct(private WPHooksRegistry $hookRegistry) {
        if (self::isActive()) return;
        $this->hookRegistry->unregisterByClass(self::class);
    }
    public static function isActive(): bool {
        return PluginList::JwtAuthenticationForWpRestApi->isActive();
    }

    #[Filter('jwt_auth_expire')]
    public function setTokenDuration(): int
    {
        $duration = 60 * 60 * 24 * 7; // 7 days
        return time() + $duration;
    }
}