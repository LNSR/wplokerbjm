<?php
namespace WPLokerBJM\Core\Hooks\Theme;
class ThemeInject
{

    /**
     * Register theme supports and image sizes.
     *
     * Adds common theme supports used across the theme:
     * - title-tag: let WP handle document title
     * - html5: modern markup for forms, galleries, captions, scripts and styles
     * - post-thumbnails: enable featured images
     * - custom-logo: configurable logo with sensible defaults
     *
     * Also registers a small set of logo image sizes used for responsive headers.
     *
     * Side effects:
     * - Calls add_theme_support() and add_image_size() during theme bootstrap.
     *
     * @return void
     */
    public static function addThemeSupport(): void
    {

        add_theme_support('title-tag');         // Add title tag support for dynamic titles
        add_theme_support('align-wide');        // Enable wide alignment for blocks
        add_theme_support('responsive-embeds'); // Responsive embeds
        // HTML5 markup for forms, galleries, captions, and scripts/styles
        add_theme_support('html5', array(
            'gallery',
            'caption',
            'script',
        ));
        add_theme_support('post-thumbnails');          // featured images

        // Register custom logo support with sensible defaults.
        // Admin can set the logo in Appearance -> Customize -> Site Identity.
        add_theme_support('custom-logo', array(
            'width' => 222,
            'height' => 64,
            'flex-height' => true,
            'flex-width' => true,
            'header-text' => array('site-title', 'site-description'),
        ));
    }

    /**
     * Get current theme logo information.
     *
     * Returns a compact associative array with the URL, responsive srcset and sizes,
     * and intrinsic image dimensions where available. This helper centralizes logic
     * for retrieving logo metadata so templates and scripts can consume a single shape.
     *
     * Behavior notes:
     * - If no custom logo is set returns empty url/srcset/sizes and fallback dimensions.
     * - Attempts to read attachment metadata for width/height; if absent, it falls back
     *   to the theme support defaults, then to a safe 128x128 fallback so browsers can
     *   compute aspect ratio reliably.
     *
     * @return array{url:string,srcset:string,sizes:string,width:int,height:int} {
     *     @type string url     Absolute URL to the logo (empty string if none)
     *     @type string srcset  Responsive srcset value produced by WP (may be empty)
     *     @type string sizes   Sizes attribute suggestion for responsive images (may be empty)
     *     @type int    width   Intrinsic pixel width (fallbacks applied)
     *     @type int    height  Intrinsic pixel height (fallbacks applied)
     * }
     */
    public static function getLogoData(): array
    {
        $custom_logo_id = get_theme_mod('custom_logo');
        if (!$custom_logo_id) {
            return ['url' => '', 'srcset' => '', 'sizes' => '', 'width' => 0, 'height' => 0];
        }

        $url = wp_get_attachment_image_url($custom_logo_id, 'full') ?: '';
        $srcset = wp_get_attachment_image_srcset($custom_logo_id, 'full') ?: '';
        $sizes = wp_get_attachment_image_sizes($custom_logo_id, 'full') ?: '';

        // Try to get intrinsic image dimensions from the attachment metadata
        $width = 0;
        $height = 0;
        $image_src = wp_get_attachment_image_src($custom_logo_id, 'full');
        if ($image_src && is_array($image_src)) {
            // wp_get_attachment_image_src returns [src, width, height]
            $width = isset($image_src[1]) ? intval($image_src[1]) : 0;
            $height = isset($image_src[2]) ? intval($image_src[2]) : 0;
        }

        // Fallback to theme support defaults if WP didn't provide dims (e.g., SVGs)
        if (!$width || !$height) {
            $support = get_theme_support('custom-logo');
            if (is_array($support) && isset($support[0]) && is_array($support[0])) {
                $supportOpts = $support[0];
                if (isset($supportOpts['width']) && isset($supportOpts['height'])) {
                    $width = intval($supportOpts['width']);
                    $height = intval($supportOpts['height']);
                }
            }
        }

        return [
            'url' => $url,
            'srcset' => $srcset,
            'sizes' => $sizes,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Provide theme runtime data for client-side hydration as an associative array.
     * 
     */
    public static function themeData(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isSSGBot = in_array($userAgent, \WPLokerBJM\Services\Utilities\SSG\BotDetection::isSsgBotGeneration(), true);
        $disableTracking = $isSSGBot || !!is_user_logged_in();

        $logoData = ThemeInject::getLogoData();
        if (empty($logoData['sizes'])) {
            $logoData['sizes'] = '(max-width: 640px) 48px, (max-width: 1024px) 64px, 128px';
        }
        $last_update = \WPLokerBJM\QueryBuilders\JobQuery::getLastModifiedDate();
        $last_update_iso = $last_update ? gmdate('c', strtotime($last_update)) : gmdate('c');

        $wpThemeData = [
            'themeUrl' => esc_url(get_stylesheet_directory_uri()),
            'logo' => esc_url($logoData['url'] ?: ''),
            'logoSrcset' => $logoData['srcset'] ?? '',
            'logoSizes' => $logoData['sizes'] ?? '',
            'logoDecoding' => 'async',
            'logoWidth' => intval($logoData['width'] ?? 0),
            'logoHeight' => intval($logoData['height'] ?? 0),
            'lastJobUpdate' => $last_update_iso,
            'disableTracking' => $disableTracking,
        ];
        return $wpThemeData;
    }

    /**
     * Echo an inline script that exposes theme runtime data to client code as JSON props for hydration.
     *
     * This outputs:
     * - A JSON script tag with theme data for hydration props.
     * - A small, non-blocking <script> that:
     *   - Stores a WP REST nonce in sessionStorage for logged-in users.
     *   - Applies a preferred color theme (localStorage > system preference) and marks the source
     *     using data-wplokerbjm-theme-sourced attribute on <html>.
     *   - Removes the <script> element after execution to avoid leaving markup traces.
     *
     * @return void
     */
    public static function injectThemeScript(): void
    {
        $wpThemeData = self::themeData(); // theme data for hydration
        ?>
        <script type="application/json" id="wp-theme-data">
                    <?= json_encode($wpThemeData); ?>
                </script>
        <script id="theme-preferences" data-no-optimize="1">
            (() => {
                function removeScriptEl() {
                    const scriptEl = document.getElementById('theme-preferences');
                    setTimeout(() => {
                        scriptEl?.remove();
                    }, 3000);
                };
                try {
                    <?php if (is_user_logged_in()): ?>
                        sessionStorage.setItem('wp-rest-nonce', '<?= esc_js(wp_create_nonce('wp_rest')); ?>');
                    <?php endif; ?>

                    const KEY = 'wplokerbjm-theme';
                    const root = document.documentElement;

                    let stored = null;
                    try { stored = localStorage.getItem(KEY); } catch (e) { stored = null; }

                    const apply = (theme) => {
                        if (!theme) return;
                        root.setAttribute('data-theme', theme);
                        root.classList.toggle('wplokerbjm-dark-mode-enable', theme === 'dark');
                    };

                    if (stored === 'dark' || stored === 'light') {
                        apply(stored);
                        root.setAttribute('data-wplokerbjm-theme-sourced', 'local');
                        removeScriptEl();
                        return;
                    }

                    let prefersDark = false;
                    try {
                        prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? false;
                    } catch (e) { prefersDark = false; }

                    apply(prefersDark ? 'dark' : 'light');
                    root.setAttribute('data-wplokerbjm-theme-sourced', 'system');
                } catch (e) {
                    console.log('fail applying theme preferences', e);
                } finally {
                    removeScriptEl();
                }
            })();
        </script>
        <?php
    }
}

class DebloatWPTheme
{
    /**
     * Dequeue and deregister any registered script whose handle contains 'jquery'.
     *
     * This attempts to remove bundled jQuery and its related variants to reduce front-end weight.
     * It is conservative: it checks that the global $wp_scripts exists and is an instance of WP_Scripts.
     *
     * Notes:
     * - This may break plugins/themes that rely on jQuery. Use only when you control/enforce
     *   frontend dependencies or provide vanilla replacements.
     *
     * @return void
     */
    public static function removeJquery(): void
    {
        global $wp_scripts;

        if (isset($wp_scripts) && ($wp_scripts instanceof WP_Scripts)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                // Check if the handle contains 'jquery' (case-insensitive)
                if (false !== stripos($handle, 'jquery')) {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                }
            }
        }
    }

    /**
     * Remove default WP block/library styles that are often not needed for custom themes.
     *
     * Deregisters/dequeues:
     * - wp-block-library
     * - wp-block-library-theme
     * - wc-block-style
     *
     * Use this to avoid loading Gutenberg's styles when building a custom-styled frontend.
     *
     * @return void
     */
    public static function removeWPLibrary(): void
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7); // must use 7
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        add_filter('emoji_svg_url', '__return_false');

        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-block-style');
        wp_dequeue_style('wp-emoji-styles');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('global-styles-inline-css');
        wp_dequeue_style('classic-theme-styles');
        wp_deregister_style('wp-block-library');
        wp_deregister_style('wp-block-library-theme');
        wp_deregister_style('wc-block-style');
        wp_deregister_style('global-styles');
        wp_deregister_style('classic-theme-styles');
        wp_deregister_style('global-styles-inline-css');
        wp_deregister_style('wp-emoji-styles');
    }
}