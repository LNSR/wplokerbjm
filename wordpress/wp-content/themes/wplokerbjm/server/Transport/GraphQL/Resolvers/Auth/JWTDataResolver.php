<?php

namespace WPLokerBJM\Transport\GraphQL\Resolvers\Auth;

use JWTDataShape;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Attributes\Injectable;
use WPLokerBJM\Services\Auth\JWT\JWTService;

/**
 * @phpstan-type JWTDataShape array{token?: string, username?: string, password?: string}
 */
#[Injectable(lazy: true)]
class JWTDataResolver
{

    public function __construct(private readonly JWTService $jwtService) {}

    /**
     * Proxy the WP JWT REST endpoints into GraphQL.
     *
     * The caller may provide either a username/password pair (to request a token)
     * or an existing token (to validate it). Returns 'ok' on success or null on failure.
     * On successful login, sets an HTTP-only SameSite=Lax cookie with the JWT.
     *
     * @param mixed $root The root Mutation object (unused)
     * @param JWTDataShape $args Mutation inputs
     * @return string|null 'ok' on success, null on failure
     */
    public function resolveJWTorValidate($root, array $args): ?string
    {
        // $args already contains our named inputs from the GraphQL field.
        $argArr = is_array($args) ? $args : [];
        $token = $argArr['token'] ?? null;
        $username = $argArr['username'] ?? null;
        $password = $argArr['password'] ?? null;
        try {
            // login if credentials present, set HTTP-only cookie on success
            if (!empty($username) && !empty($password)) {
                return $this->jwtService->validateCredentialWP($username, $password);
            }
            // if only token provided we validate, no cookie change on validation
            if (!empty($token)) {
                return $this->jwtService->validateOnlyToken($token);
            }
        } catch (\Exception $e) {
            SharedUtils::isDevelopment() && Logger::error(__CLASS__, 'ThemeDataResolver::resolveJWTorValidate error: ' . $e->getMessage());
        }

        return null;
    }

}
