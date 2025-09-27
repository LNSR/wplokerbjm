<?php

namespace AstraChild\Core\Enqueue;

/**
 * Encapsulates Vite dev/prod enqueue logic.
 */
class Vite
{
    public static array $noOptimizeStyleHandles = [];

    public static function viteEntry(): string
    {
        static $entry = null;
        if ($entry !== null) {
            return $entry;
        }

        $dist_dir = get_stylesheet_directory() . '/assets/dist';
        $manifest_path = $dist_dir . '/.vite/manifest.json';

        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            if (isset($manifest['src/main.ts'])) {
                $entry = 'main.ts';
            } elseif (isset($manifest['src/inversify.config.ts'])) {
                $entry = 'inversify.config.ts';
            } else {
                error_log('Vite::viteEntry error: Neither main.ts nor inversify.config.ts found in manifest.json');
            }
        } else {
            error_log('Vite::viteEntry error: manifest.json not found at ' . $manifest_path);
        }

        return $entry;
    }

    public static function isDevelopment(): bool
    {
        return defined('WP_ENV') && WP_ENV === 'development';
    }

    /**
     * Enqueue scripts when running the Vite dev server.
     * No preload values are returned anymore.
     */
    public static function enqueueForDevelopment(): array
    {
        $vite_base_url = rtrim(home_url(), '/') . ':5173';
        $vite_handle = 'vite-' . md5(self::viteEntry());
        $client_handle = 'vite-client';

        // Enqueue module scripts for dev server
        wp_enqueue_script_module(
            $vite_handle,
            "{$vite_base_url}/src/" . self::viteEntry(),
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
    }

    /**
     * Enqueue built assets for production.
     * Returns array with keys: noOptimizeStyleHandles, assetVersion
     */
    public static function enqueueForProduction(): array
    {
        $dist_dir = get_stylesheet_directory() . '/assets/dist';
        $dist_uri = get_stylesheet_directory_uri() . '/assets/dist';
        $manifest_path = $dist_dir . '/.vite/manifest.json';

        if (!file_exists($manifest_path)) {
            return [];
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        $manifest_key = 'src/' . self::viteEntry();
        if (empty($manifest[$manifest_key]['file'])) {
            return [];
        }

        $main_js = $manifest[$manifest_key]['file'];
        $main_css = $manifest[$manifest_key]['css'] ?? [];

        $vue_handle = 'vue-' . md5(self::viteEntry());
        wp_enqueue_script_module(
            $vue_handle,
            $dist_uri . '/' . $main_js,
            [],
            filemtime($dist_dir . '/' . $main_js)
        );

        foreach ($main_css as $css_file) {
            $css_handle = $vue_handle . '-' . md5($css_file);
            wp_enqueue_style(
                $css_handle,
                $dist_uri . '/' . $css_file,
                [],
                filemtime($dist_dir . '/' . $main_js)
            );
            self::$noOptimizeStyleHandles[] = $css_handle;
        }

        return [
            'noOptimizeStyleHandles' => self::$noOptimizeStyleHandles
        ];
    }
}