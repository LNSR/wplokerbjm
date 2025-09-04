<?php

namespace AstraChild\Core\Enqueue;

/**
 * Encapsulates Vite dev/prod enqueue logic.
 */
class Vite
{

    private function getViteEntry(): string
    {
        return 'main.ts';
    }

    public function isDevelopment(): bool
    {
        return defined('WP_ENV') && WP_ENV === 'development';
    }

    private function getDevBaseUrl(): string
    {
        return rtrim(home_url(), '/') . ':5173';
    }

    /**
     * Enqueue scripts when running the Vite dev server.
     * No preload values are returned anymore.
     */
    public function enqueueForDevelopment(): array
    {
        try {
            $vite_base_url = $this->getDevBaseUrl();
            $vite_handle = 'vite-' . md5($this->getViteEntry());
            $client_handle = 'vite-client';

            // Enqueue module scripts for dev server
            wp_enqueue_script_module(
                $vite_handle,
                "{$vite_base_url}/src/{$this->getViteEntry()}",
                [],
                null
            );

            wp_enqueue_script_module(
                $client_handle,
                "{$vite_base_url}/@vite/client",
                [],
                null
            );

            return [];
        } catch (\Exception $e) {
            error_log('Vite::enqueueForDevelopment error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Enqueue built assets for production.
     * Returns array with keys: noOptimizeStyleHandles, assetVersion
     */
    public function enqueueForProduction(): array
    {
        try {
            $dist_dir = get_stylesheet_directory() . '/assets/dist';
            $dist_uri = get_stylesheet_directory_uri() . '/assets/dist';
            $manifest_path = $dist_dir . '/.vite/manifest.json';

            if (!file_exists($manifest_path)) {
                return [];
            }

            $manifest = json_decode(file_get_contents($manifest_path), true);
            $manifest_key = 'src/' . $this->getViteEntry();
            if (empty($manifest[$manifest_key]['file'])) {
                return [];
            }

            $main_js = $manifest[$manifest_key]['file'];
            $main_css = $manifest[$manifest_key]['css'] ?? [];

            $vue_handle = 'vue-' . md5($this->getViteEntry());
            wp_enqueue_script_module(
                $vue_handle,
                $dist_uri . '/' . $main_js,
                [],
                filemtime($dist_dir . '/' . $main_js)
            );

            $noOptimizeStyleHandles = [];

            foreach ($main_css as $css_file) {
                $css_handle = $vue_handle . '-' . md5($css_file);
                wp_enqueue_style(
                    $css_handle,
                    $dist_uri . '/' . $css_file,
                    [],
                    filemtime($dist_dir . '/' . $css_file)
                );
                $noOptimizeStyleHandles[] = $css_handle;
            }

            return [
                'noOptimizeStyleHandles' => $noOptimizeStyleHandles
            ];
        } catch (\Exception $e) {
            error_log('Vite::enqueueForProduction error: ' . $e->getMessage());
            return [];
        }
    }
}
