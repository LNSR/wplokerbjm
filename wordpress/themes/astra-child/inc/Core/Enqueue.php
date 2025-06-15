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

    /**
     * Determine the correct Vite entry for the current page.
     */
    private function getViteEntry(): ?string
    {
        if (is_front_page() || is_post_type_archive('lowongan') || is_page('pasang-iklan-loker') ) {
            return 'homepage.ts';
        } elseif (is_singular('lowongan')) {
            return 'single.ts';
        }
        return null;
    }

    public function enqueueAssets(): void
    {
        $entry = $this->getViteEntry();
        if (!$entry) {
            return;
        }

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

        // Enqueue CSS from imported chunks (e.g., vendor, vendor-swiper, etc.)
        if (!empty($manifest[$manifest_key]['imports'])) {
            foreach ($manifest[$manifest_key]['imports'] as $importKey) {
                if (!empty($manifest[$importKey]['css'])) {
                    foreach ($manifest[$importKey]['css'] as $importCss) {
                        wp_enqueue_style(
                            'vue-' . $importKey,
                            $dist_uri . '/' . $importCss,
                            [],
                            filemtime($dist_dir . '/' . $importCss)
                        );
                    }
                }
            }
        }
    }
}
