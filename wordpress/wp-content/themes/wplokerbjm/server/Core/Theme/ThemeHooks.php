<?php
namespace WPLokerBJM\Core\Theme;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\GlobalHooks;
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
    #[Action('after_setup_theme')]
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
     * Adds additional site icon meta tags for custom sizes.
     */
    #[Filter('site_icon_meta_tags')]
    public static function addSiteIconMetaTags(array $meta_tags): array
    {

        $additional_sizes = [48, 96, 144, 256, 384, 512];

        foreach ($additional_sizes as $size) {
            $url = get_site_icon_url($size);
            if ($url) {
                $meta_tags[] = sprintf('<link rel="icon" href="%s" sizes="%dx%d" />', esc_url($url), $size, $size);
            }
        }

        // Add PNG fallback pointing directly to the original uploaded favicon
        $original_url = wp_get_attachment_url(get_option('site_icon'));
        if ($original_url) {
            // Replace .avif extension with .png for PNG fallback
            $original_url = str_replace('cropped-site-icon.avif', 'site-icon.png', $original_url);
            $meta_tags[] = sprintf('<link rel="icon" href="%s" sizes="600x600" data-title-attribute="Favicon PNG fallback" />', esc_url($original_url));
        }

        $addTypeAttribute = function (&$meta_tags, $type) {
            foreach ($meta_tags as &$tag) {
                // For link tags (icon and apple-touch-icon)
                if (preg_match('/<link (?:rel="icon"|rel="apple-touch-icon")[^>]*href="[^"]*\.' . preg_quote($type, '/') . '"[^>]*>/', $tag) && !str_contains($tag, 'type=')) {
                    $tag = str_replace(' />', ' type="image/' . $type . '" />', $tag);
                }
                // For meta msapplication-TileImage
                if (preg_match('/<meta name="msapplication-TileImage"[^>]*content="[^"]*\.' . preg_quote($type, '/') . '"[^>]*>/', $tag) && !str_contains($tag, 'type=')) {
                    $tag = str_replace(' />', ' type="image/' . $type . '" />', $tag);
                }
            }
        };

        foreach (['png', 'ico', 'svg', 'webp', 'avif'] as $type) {
            $addTypeAttribute($meta_tags, $type);
        }

        return $meta_tags;
    }

    /**
     * Provide additional site icon image sizes for generation.
     *
     * This filter adds a set of common icon sizes to be generated when a site icon is set.
     * It complements the default sizes provided by WordPress.
     *
     * @return int[] Array of additional icon sizes in pixels.
     */
    #[Filter('site_icon_image_sizes')]
    public static function siteIconImageSizes(): array
    {
        return [32, 48, 96, 144, 192, 256, 384, 512];
    }

    /**
     * Output preload <link> for the logo image.
     *
     * If a custom logo is set, this outputs a <link rel="preload" as="image"> tag
     * with appropriate srcset and sizes attributes for responsive loading.
     * This helps prioritize logo loading for better performance and user experience.
     *
     * Side effects:
     * - Echoes HTML directly to the output buffer.
     *
     * @return void
     */
    #[Action('wp_head')]
    public static function preloadLogo(): void
    {
        $logoData = self::getLogoData();
        if (empty($logoData['url'])) {
            return;
        }

        $Attrs = [
            'rel' => 'preload',
            'as' => 'image',
            'href' => esc_url($logoData['url']),
            'imagesrcset' => esc_attr($logoData['srcset'] ?: ''),
            'imagesizes' => esc_attr($logoData['sizes'] ?: ''),
            'fetchpriority' => 'high',
        ];

        $preloadAttrs = array_filter($Attrs, fn($value) => !empty($value));
        echo '<link ' . implode(' ', array_map(
            fn($key, $value) => $key . '="' . $value . '"',
            array_keys($preloadAttrs),
            $preloadAttrs
        )) . ' />' . "\n";
    }

    /**
     * Provide theme runtime data for client-side hydration as an associative array.
     *
     * The array contains:
     * - themeUrl (string)
     * - logo: nested logo metadata
     * - lastJobUpdate, lastTaxonomyUpdate (ISO strings)
     * - disableTracking (bool)
     * - themeVersion (int)
     * - wpRestNonce (string, when logged in)
     * - siteIconTags (string): newline‑separated <link> tags generated via the
     *   `site_icon_meta_tags` filter. Useful for rendering favicon markup in
     *   head elements when hydrating client code.
     */
    public static function themeData(): array
    {
        $loggedIn = is_user_logged_in();
        // For logged-in users store per-user caches to avoid leaking any per-user secrets (nonces)
        $cacheKey = $loggedIn
            ? CacheKey::THEME_DATA . '_user_' . (int) get_current_user_id()
            : CacheKey::THEME_DATA . '_anonymous';
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            // override disableTracking and wpRestNonce for logged-in state
            $cached['disableTracking'] = $loggedIn;
            if ($loggedIn) {
                $cached['wpRestNonce'] = wp_create_nonce('wp_rest');
            } else {
                // safety remove in case cached from logged-in
                if (isset($cached['wpRestNonce'])) {
                    unset($cached['wpRestNonce']);
                }
            }
            return $cached;
        }


        $logoData = ThemeInject::getLogoData();
        if (empty($logoData['sizes'])) {
            $logoData['sizes'] = '(max-width: 640px) 48px, (max-width: 1024px) 64px, 128px';
        }
        $last_update = \WPLokerBJM\QueryBuilders\JobQuery::getLastModifiedDate();
        $last_update_iso = $last_update ? gmdate('c', strtotime($last_update)) : gmdate('c');

        // compute optional site icon <link> tags using the same filter used in addSiteIconMetaTags()
        $siteIconTags = '';
        $tags = apply_filters('site_icon_meta_tags', []);
        if (!empty($tags) && is_array($tags)) {
            $siteIconTags = implode("\n", $tags);
        }

        $wpThemeData = [
            'themeUrl' => esc_url(get_stylesheet_directory_uri()),
            'logo' => [
                'logoUrl' => $logoData['url'] ?? '',
                'logoSrcset' => $logoData['srcset'] ?? '',
                'logoSizes' => $logoData['sizes'] ?? '',
                'logoDecoding' => 'async',
                'logoWidth' => intval($logoData['width'] ?? 0),
                'logoHeight' => intval($logoData['height'] ?? 0),
            ],
            'lastJobUpdate' => $last_update_iso,
            'lastTaxonomyUpdate' => \WPLokerBJM\QueryBuilders\TaxonomyQuery::getLastModifiedDateForTaxonomies(),
            'disableTracking' => $loggedIn,
            'themeVersion' => (int) filemtime(get_stylesheet_directory() . '/composer.json'),
            'siteIconTags' => $siteIconTags,
        ];

        if ($loggedIn) {
            $wpThemeData['wpRestNonce'] = wp_create_nonce('wp_rest');
        }

        Cache::set($cacheKey, $wpThemeData, 86400); // Cache for 1 day

        return $wpThemeData;
    }
}