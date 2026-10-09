<?php
namespace WPLokerBJM\Core\Redirect;

use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
/*======================================================================
 | REDIRECTS
 ======================================================================*/

/**
 * Handles all template_redirect hooks: SSL bypass, 410 Gone, archive → home,
 * and headless SvelteKit frontend routing.
 */
class RedirectHooks
{
    /**
     * Return 410 Gone for old job posts that have been trashed due to age.
     * * For deleted job posts (404 on single lowongan), return 410 Gone.
     * ! Notify search engines with 410 Gone for removed job posts.
     */
    #[Action(
        'template_redirect',
        2,
        deferRegisterUntilHook: 'init',
        once: true,
        registerIf: static function (): bool {
            return self::shouldRegister();
        },
        executeIf: static function (): bool {
            return !self::shouldSkipRedirect() && !self::isDraftPreviewRequest() && is_404() && is_singular(PostTypes::POST_TYPE_LOWONGAN);
        },
    )]
    public function oldPost410Redirect(): void
    {
        status_header(410);
        wp_die('This job posting has been expired or removed.', 'Gone', ['response' => 410]);

        // Other 404s: redirect to the headless Svelte frontend
        $baseUrl = SharedUtils::headlessDomainRedirect();
        wp_redirect(rtrim($baseUrl, '/') . '/', 302);
        exit;
    }

    /**
     * Headless frontend redirect.
     * Runs early during `template_redirect` so the theme always forwards
     * public requests to the Svelte frontend (dev vs prod).
     */
    #[Action(
        'template_redirect',
        3,
        deferRegisterUntilHook: 'init',
        once: true,
        registerIf: static function (): bool {
            return self::shouldRegister();
        },
        executeIf: static function (): bool {
            return !self::shouldSkipRedirect();
        },
    )]
    public function headlessFrontendAdminSideRedirect(): void
    {
        $baseUrl = SharedUtils::headlessDomainRedirect();

        $path = '/';
        if ((is_page('pasang-iklan-loker') || is_page(184))) {
            $path = '/pasang-iklan-loker';
        } elseif (is_page('kebijakan-privasi')) {
            $path = '/kebijakan-privasi';
        } elseif (self::isDraftPreviewRequest()) {
            // Draft/pending lowongan: normalize to ID-based URL so the
            // frontend preview route can resolve it without the redundant
            // ?post_type=lowongan&p= query string.
            $path = '/' . PostTypes::POST_TYPE_LOWONGAN . '/' . (int) $_GET['p'];
        } elseif (is_single() && get_post_type() === PostTypes::POST_TYPE_LOWONGAN) {
            $post = get_post();
            if ($post && !empty($post->post_name)) {
                $path = '/' . PostTypes::POST_TYPE_LOWONGAN . '/' . $post->post_name;
            }
        } elseif (is_post_type_archive(PostTypes::POST_TYPE_LOWONGAN) || is_front_page() || is_page(146)) {
            $path = '/';
        }

        $query = '';
        if (!self::isDraftPreviewRequest()) {
            $query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
        }
        $location = rtrim($baseUrl, '/') . $path . $query;

        wp_redirect($location, 302);
        exit;
    }
    /**
     * Skip redirect logic for ACME challenge requests and AutoSSL probe user agents.
     */
    private static function shouldBypassAutoSsl(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($requestUri !== '' && str_starts_with($requestUri, '/.well-known/acme-challenge/')) {
            return true;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($userAgent === '') {
            return false;
        }

        return stripos($userAgent, 'litespeed') !== false || stripos($userAgent, 'quic-cloud') !== false;
    }

    /**
     * Common early-return guard for all template_redirect hooks.
     *
     * Consolidates checks that apply universally: SSL bypass probes,
     * backend-only contexts (REST, AJAX, CLI, cron, preview), and
     * special query-parameter bypass flags.
     */
    private static function shouldSkipRedirect(): bool
    {
        if (self::shouldBypassAutoSsl()) {
            return true;
        }

        if (wp_doing_cron()) {
            return true;
        }

        return false;
    }

    private static function shouldRegister(): bool
    {

        if (
            (defined('GRAPHQL_REQUEST') && GRAPHQL_REQUEST) ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            (defined('DOING_AJAX') && DOING_AJAX) ||
            SharedUtils::isWPCLI() ||
            isset($_GET['_wfsf']) // WordFence query
        ) {
            return false;
        }

        return true;
    }

    /**
     * Detect draft-preview intent: a request to view a non-published lowongan
     * by ID (e.g. `?post_type=lowongan&p=15941` from the editor's save-draft
     * flow). These must NOT be treated as 404/410 — they are preview requests
     * that the headless redirect forwards to the frontend preview route.
     */
    private static function isDraftPreviewRequest(): bool
    {
        if (empty($_GET['p'])) {
            return false;
        }

        $post = get_post((int) $_GET['p']);
        if (!$post instanceof \WP_Post || $post->post_type !== PostTypes::POST_TYPE_LOWONGAN) {
            return false;
        }

        return in_array($post->post_status, ['draft', 'pending', 'future', 'private'], true);
    }
}
