<?php
declare(strict_types=1);
namespace WPLokerBJM\Core\Plugins;

use WPLokerBJM\Core\Container\Attributes\Filter;

/**
 * JWT Auth Hooks
 * @link https://github.com/Tmeister/wp-api-jwt-auth
 */
class JWTHooks
{
    #[Filter("jwt_auth_expire")]
    public function setTokenDuration(): int
    {
        $duration = 60 * 60 * 24 * 7; // 7 days
        return time() + $duration;
    }
}