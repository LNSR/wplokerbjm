<?php
namespace WPLokerBJM\Core\Wordpress\Plugins\ThirdParty;
use \DI\Attribute\Injectable;
use WPLokerBJM\Core\Container\Attributes\Filter;
use WPLokerBJM\Core\Wordpress\Plugins\PluginConfigInterface;
use WPLokerBJM\Core\Wordpress\Plugins\PluginList;
use WPLokerBJM\Shared\Utilities\{SharedUtils};
/**
 * JWT Auth Hooks
 * @link https://github.com/Tmeister/wp-api-jwt-auth
 */
final class WPRestJWTHooks implements PluginConfigInterface
{
    public static function isActive(): bool
    {
        return PluginList::JwtAuthenticationForWpRestApi->isActive();
    }

    #[Filter('jwt_auth_expire')]
    public function setTokenDuration(): int
    {
        $duration = 60 * 60 * 24 * 1; // 1 days
        return time() + $duration;
    }
}