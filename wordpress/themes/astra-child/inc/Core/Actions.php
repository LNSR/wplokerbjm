<?php

namespace AstraChild\Core;

class Actions
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'injectThemeScript'], 1);
    }

    /**
     * Injects a script to set the theme before CSS loads, preventing FOUC.
     */
    public function injectThemeScript(): void
    {
?>
        <script>
            (function() {
                try {
                    let theme = localStorage.getItem('astra-theme');
                    if (theme === 'dark' || theme === 'light') {
                        document.documentElement.setAttribute('data-theme', theme);
                    }
                } catch (e) {}
            })();
        </script>
<?php
    }
}
