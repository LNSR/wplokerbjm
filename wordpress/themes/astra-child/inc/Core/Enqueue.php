<?php

namespace AstraChild\Core;

class Enqueue
{
    /**
     * Register scripts and styles.
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    private function getViteEntry(): string
    {
        return 'main.ts';
    }

    public function enqueueAssets(): void
    {
        $entry = $this->getViteEntry();

        // Development mode: load from Vite dev server
        if (defined('WP_ENV') && WP_ENV === 'development') {
            $vite_base_url = preg_replace('#/$#', '', home_url()) . ':5173';
            wp_enqueue_script_module(
                'vite-' . md5($entry),
                "{$vite_base_url}/src/{$entry}",
                [],
                null
            );
            wp_enqueue_script_module(
                'vite-client',
                "{$vite_base_url}/@vite/client",
                [],
                null
            );
            return;
        }

        // Production mode: load from built assets
        $dist_dir = get_stylesheet_directory() . '/assets/vue/dist';
        $dist_uri = get_stylesheet_directory_uri() . '/assets/vue/dist';
        $manifest_path = $dist_dir . '/.vite/manifest.json';

        if (!file_exists($manifest_path)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        $manifest_key = 'src/' . $entry;

        if (empty($manifest[$manifest_key]['file'])) {
            return;
        }

        $main_js = $manifest[$manifest_key]['file'];
        $main_css = $manifest[$manifest_key]['css'] ?? [];
        $main_assets = $manifest[$manifest_key]['assets'] ?? [];

        // Enqueue main JS
        wp_enqueue_script_module(
            'vue-' . md5($entry),
            $dist_uri . '/' . $main_js,
            [],
            filemtime($dist_dir . '/' . $main_js)
        );

        // Enqueue main CSS
        foreach ($main_css as $css_file) {
            wp_enqueue_style(
                'vue-' . md5($entry) . '-' . md5($css_file),
                $dist_uri . '/' . $css_file,
                [],
                filemtime($dist_dir . '/' . $css_file)
            );
        }

        $asset_version = filemtime($dist_dir . '/' . $main_js);
        $this->injectAssetVersionScript($asset_version);
    }

    /**
     * Injects a script to clear storage and reload if asset version changes.
     */
    private function injectAssetVersionScript($asset_version): void
    {
        add_action('wp_head', function () use ($asset_version) {
            ?>
            <script>
                (function() {
                    var ASSET_VERSION = '<?php echo $asset_version; ?>';
                    var STORAGE_KEY = 'asset_version';
                    try {
                        var current = localStorage.getItem(STORAGE_KEY);
                        if (current && current !== ASSET_VERSION) {
                            localStorage.clear();
                            sessionStorage.clear();
                            if ('caches' in window) {
                                caches.keys().then(function(names) {
                                    for (let name of names) caches.delete(name);
                                });
                            }
                            location.reload(true);
                        }
                        localStorage.setItem(STORAGE_KEY, ASSET_VERSION);
                    } catch (e) {}
                })();
            </script>
            <?php
        });
    }
}
