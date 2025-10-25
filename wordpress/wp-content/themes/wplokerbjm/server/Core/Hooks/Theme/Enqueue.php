<?php

namespace WPLokerBJM\Core\Hooks\Theme;
use WPLokerBJM\Core\ObjectCache;

class Enqueue
{
    public static function enqueueAssets(): void
    {
        try {
            if (Vite::isDevelopment()) {
                Vite::enqueueForDevelopment();
                return;
            }

            $prod = Vite::enqueueForProduction();
            if (empty($prod)) {
                return;
            }
            // No need to merge, just use the static property in filterStyleLoaderTag
        } catch (\Exception $e) {
            error_log('Enqueue::enqueueAssets error: ' . $e->getMessage());
        }
    }

    /**
     * Filter callback for style_loader_tag.
     * Adds data-no-optimize attribute to specific styles.
     */
    public static function filterStyleLoaderTag(string $tag, string $handle): string
    {
        try {
            if (in_array($handle, Vite::$noOptimizeStyleHandles, true)) {
                return str_replace('<link ', '<link data-no-optimize="1" ', $tag);
            }
            return $tag;
        } catch (\Exception $e) {
            error_log('Enqueue::filterStyleLoaderTag error: ' . $e->getMessage());
            return $tag;
        }
    }
}

/**
 * Encapsulates Vite dev/prod enqueue logic.
 */
class Vite
{
    public static array $noOptimizeStyleHandles = [];

    private static function viteEntry(): string
    {
        $entry = null;
        if ($entry !== null) {
            return $entry;
        }

        $manifest = self::getManifest();
        if ($manifest !== null) {
            foreach ($manifest as $key => $value) {
                if (isset($value['isEntry']) && $value['isEntry'] === true) {
                    $entry = $key;
                    break;
                }
            }
        }

        return $entry;
    }

    /**
     * Get the Vite manifest from cache or file.
     */
    private static function getManifest(): ?array
    {
        $dist_dir = get_stylesheet_directory() . '/assets/dist';
        $manifest_path = $dist_dir . '/.vite/manifest.json';

        $manifest = ObjectCache::get('vite_manifest');
        if ($manifest === false) {
            if (!file_exists($manifest_path)) {
                return null;
            }
            $manifest = json_decode(file_get_contents($manifest_path), true);
            ObjectCache::set('vite_manifest', $manifest, expiration: 81600); // Cache for 1 day
        }
        return $manifest;
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
        $vite_handle = 'vite-entry';
        $client_handle = 'vite-client';

        // Enqueue module scripts for dev server
        wp_enqueue_script_module(
            $vite_handle,
            "{$vite_base_url}/" . self::viteEntry(),
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

        $manifest = self::getManifest();
        if ($manifest === null) {
            return [];
        }
        $manifest_key = self::viteEntry();
        if (empty($manifest[$manifest_key]['file'])) {
            return [];
        }

        $main_js = $manifest[$manifest_key]['file'];
        $main_css = $manifest[$manifest_key]['css'] ?? [];

        $svelte_handle = 'svelte-boot';
        wp_enqueue_script_module(
            $svelte_handle,
            $dist_uri . '/' . $main_js,
            [],
            filemtime($dist_dir . '/' . $main_js)
        );

        foreach ($main_css as $css_file) {
            $css_handle = $svelte_handle . '-' . str_replace('.', '-', basename($css_file));
            wp_enqueue_style(
                $css_handle,
                $dist_uri . '/' . $css_file,
                [],
                filemtime($dist_dir . '/' . $main_js)
            );
            self::$noOptimizeStyleHandles[] = $css_handle;
        }

        return [
            'noOptimizeStyleHandles' => self::$noOptimizeStyleHandles,
        ];
    }
}