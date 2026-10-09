<?php

namespace WPLokerBJM\Core\Wordpress;

use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\WPLokerBJMBootstrap;

/*======================================================================
 | Temporary Global Hooks Classes Purgatory
 ======================================================================*/

/*======================================================================
 | META TAGS / SEO
 ======================================================================*/

/**
 * Adds noindex and nofollow directives to robots meta tag.
 * - Noindex for lowongan post type archive page.
 * - Noindex,nofollow for staging/dev subdomains.
 */
class RobotsHooks
{
    #[Filter('wp_robots')]
    public function __invoke(array $robots): array
    {
        if (is_post_type_archive(PostTypes::POST_TYPE_LOWONGAN)) {
            $robots['noindex'] = true;
        }

        if (defined('WP_LOKERBJM_NO_INDEX') && WP_LOKERBJM_NO_INDEX) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }

        return $robots;
    }
}

/*======================================================================
 | LANGUAGE HOOKS
 ======================================================================*/

/**
 * Force locale to Indonesian on the frontend for consistent user experience,
 * while keeping admin in English.
 */
class LanguageHooks
{
    #[Filter(
        'locale',
        registerIf: static function (): bool {
            return !is_admin();
        },
    )]
    public function frontendLocalHTMLl10n(string $locale): string
    {
        return $locale = 'id_ID';
    }
}

/*======================================================================
 | HTTP HOOKS
 ======================================================================*/
class HTTPHooks
{
    //** forwarded IP from the SvelteKit frontend
    #[Action(
        'mu_plugin_loaded',
        PHP_INT_MIN,
        registerIf: static function (): bool {
            return !SharedUtils::isDevelopment() && !SharedUtils::isWPCLI();
        },
        executeIf: static function (WPLokerBJMBootstrap $bootstrap, string $pluginPath): bool {
            if ($pluginPath !== $bootstrap->nameMUpluginFile) return false;
            \do_action(ContainerRegistryHookEvent::UNREGISTER_BY_CALLABLE, [__CLASS__, 'setRemoteAddr']);
            return true;
        }
    )]
    public function setRemoteAddr($pluginPath): void
    {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
            return;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
    }
}