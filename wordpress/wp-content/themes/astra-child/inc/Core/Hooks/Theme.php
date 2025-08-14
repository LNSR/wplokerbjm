<?php
namespace AstraChild\Core\Hooks;

class Theme
{

    public static function injectNoScriptWarning(): void
    {
        ?>
        <noscript>
            <div class="fixed top-0 left-0 w-full z-[9999] bg-yellow-300 text-black text-center font-bold py-4 px-2 mt-12">
                Tolong aktifkan JavaScript di browser Anda.
            </div>
        </noscript>
        <?php
    }

    /**
     * Injects a meta attribute indicating if the user is logged in to WordPress.
     */
    public static function injectWpUserLoggedInFlag(): void
    {
        if (is_user_logged_in()) {
            ?>
            <meta name="wp-user-logged-in" content="true">
            <meta name="wp-rest-nonce" content="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
            <?php
        }
    }

    public static function injectThemeScript(): void
    {
        ?>
        <script data-no-optimize="1">
            (function () {
                try {
                    var KEY = 'astra-theme';
                    var root = document.documentElement;

                    var stored = null;
                    try { stored = localStorage.getItem(KEY); } catch (e) { stored = null; }

                    function apply(theme) {
                        if (!theme) return;
                        root.setAttribute('data-theme', theme);
                        if (theme === 'dark') {
                            root.classList.add('astra-dark-mode-enable');
                        } else {
                            root.classList.remove('astra-dark-mode-enable');
                        }
                    }

                    if (stored === 'dark' || stored === 'light') {
                        apply(stored);
                        root.setAttribute('data-astra-theme-sourced', 'local');
                        return;
                    }

                    var prefersDark = false;
                    try {
                        prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    } catch (e) { prefersDark = false; }

                    apply(prefersDark ? 'dark' : 'light');
                    root.setAttribute('data-astra-theme-sourced', 'system');
                } catch (e) {
                }
            })();
        </script>
        <?php
    }
}