<?php
namespace WPLokerBJM\Core\Hooks;

class Theme
{

    /**
     * Register theme supports such as custom logo so WP admin can set site logo.
     */
    public static function addThemeSupport(): void
    {
        // Add title tag support for dynamic titles
        add_theme_support('title-tag');
        // HTML5 markup for forms, galleries, captions, and scripts/styles
        add_theme_support('html5', array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'script',
            'style',
        ));
        add_theme_support('automatic-feed-links');     // RSS links in head
        add_theme_support('post-thumbnails');          // featured images

        // Register custom logo support with sensible defaults.
        // Admin can set the logo in Appearance -> Customize -> Site Identity.
        add_theme_support('custom-logo', array(
            'height' => 120,
            'width' => 400,
            'flex-height' => true,
            'flex-width' => true,
            'header-text' => array('site-title', 'site-description'),
        ));
    }

    public static function injectThemeScript(): void
    {
        $logo = (function (): string|bool{
            $custom_logo_id = get_theme_mod('custom_logo');
            return $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : false;
        });
        $last_update = \WPLokerBJM\QueryBuilders\JobQuery::getLastModifiedDate();
        $last_update_iso = $last_update ? gmdate('c', strtotime($last_update)) : gmdate('c');
        ?>
        <script data-no-optimize="1">
            (function () {
                try {
                    window.wpTheme = window.wpTheme || {};
                    window.wpTheme.themeUrl = '<?= esc_js(esc_url(get_stylesheet_directory_uri())); ?>';
                    window.wpTheme.logo = '<?= esc_js(esc_url($logo() ?: '')); ?>';
                    window.wpTheme.lastJobUpdate = '<?= esc_js($last_update_iso); ?>';
                    window.wpTheme.loggedIn = <?= is_user_logged_in() ? 'true' : 'false'; ?>;

                    var KEY = 'wplokerbjm-theme';
                    var root = document.documentElement;

                    var stored = null;
                    try { stored = localStorage.getItem(KEY); } catch (e) { stored = null; }

                    function apply(theme) {
                        if (!theme) return;
                        root.setAttribute('data-theme', theme);
                        if (theme === 'dark') {
                            root.classList.add('wplokerbjm-dark-mode-enable');
                        } else {
                            root.classList.remove('wplokerbjm-dark-mode-enable');
                        }
                    }

                    if (stored === 'dark' || stored === 'light') {
                        apply(stored);
                        root.setAttribute('data-wplokerbjm-theme-sourced', 'local');
                        return;
                    }

                    var prefersDark = false;
                    try {
                        prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    } catch (e) { prefersDark = false; }

                    apply(prefersDark ? 'dark' : 'light');
                    root.setAttribute('data-wplokerbjm-theme-sourced', 'system');
                } catch (e) {
                }
            })();
            document.currentScript.remove();
        </script>
        <?php
    }

    /**
     * Unregister and dequeue unneeded WordPress scripts and styles to avoid frontend bloat.
     *
     * Purpose: keep the theme output lean by removing unneeded core/plugin assets when
     * the theme doesn't rely on them.
     *
     * Safety: the function checks that the global `$wp_scripts` is available and is
     * an instance of `WP_Scripts` before iterating registered handles.
     *
     * @return void
     */
    public static function unregisterUnneededWPScripts(): void
    {
        global $wp_scripts;

        if (isset($wp_scripts) && ($wp_scripts instanceof WP_Scripts)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                // Check if the handle contains 'jquery' (case-insensitive)
                if (false !== stripos($handle, 'jquery')) {
                    // If the script was enqueued, dequeue it first, then deregister.
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                }
            }
        }

        // Dequeue and deregister hoverintent if not needed
        wp_dequeue_script('hoverintent-js');
        wp_deregister_script('hoverintent-js');

        // Dequeue and deregister block library and related styles
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-block-style');
        wp_deregister_style('wp-block-library');
        wp_deregister_style('wp-block-library-theme');
        wp_deregister_style('wc-block-style');
    }
}