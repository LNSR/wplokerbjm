<?php
namespace WPLokerBJM\Services\GraphQL\Hooks\HTTP;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\Integrations\LiteSpeedGraphQLIntegration;
use WPLokerBJM\Services\GraphQL\ETag\WPGraphQLETag;
use WPLokerBJM\Shared\Utilities\SharedUtils;
final class BootGraphQLRequest
{

    public function __construct(private LiteSpeedGraphQLIntegration $litespeedGraphQLIntegration, private WPGraphQLETag $eTag)
    {
    }
    
    /**
     * Unified init request handler: checks ETag cache before performing auth.
     */
    #[Action('init_graphql_request', once: true, tag: ['graphql'], deferRegister: true)]
    public function handleInitRequest(): void
    {
        $this->litespeedGraphQLIntegration->setCacheable();
        $this->authenticateViaCookie();
        $this->eTag->checkEarly304AndExit();
    }

    /**
     * Authenticate GraphQL requests
     * Must be logged in Wordpress to have the cookies, but this allows GraphQL requests to be authenticated for decoupled frontend.
     */
    private function authenticateViaCookie(): void
    {
        $cookie = SharedUtils::getWordpressAuthCookie();
        if (empty($cookie)) {
            return;
        }
        $this->injectJwtFromCookie();

        // Validate the cookie value using WP helper
        // choose scheme based on cookie type: secure login cookies use the secure_auth scheme
        $scheme = str_starts_with($cookie['name'], 'wordpress_sec_') ? 'secure_auth' : 'logged_in';
        $user_id = wp_validate_auth_cookie($cookie['value'], $scheme);
        if ($user_id !== false) {
            wp_set_current_user((int) $user_id);
            wp_get_current_user();
        }
    }
    /**
     * Inject the JWT from the HttpOnly cookie as a Bearer token so the JWT
     * authentication plugin can authenticate the request transparently.
     */
    private function injectJwtFromCookie(): void
    {
        if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_COOKIE['jwt-token'])) {
            $bearer = 'Bearer ' . $_COOKIE['jwt-token'];
            $_SERVER['HTTP_AUTHORIZATION'] = $bearer;
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $bearer;
        }
    }
}
