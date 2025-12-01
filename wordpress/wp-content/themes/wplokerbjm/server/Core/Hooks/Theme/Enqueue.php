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
     * Output preload links for route-specific JS and CSS assets.
     * Production only.
     */
    public static function outputPreloadLinks(): void
    {
        try {
            if (Vite::isDevelopment()) {
                return;
            }

            $urls = Vite::getPreloadUrls($_SERVER['REQUEST_URI'] ?? '/');
            foreach ($urls as $url) {
                if (str_ends_with($url, '.js')) {
                    echo '<link rel="modulepreload" as="script" crossorigin href="' . esc_url($url) . '">' . "\n";
                } elseif (str_ends_with($url, '.css')) {
                    echo '<link rel="preload" as="style" crossorigin href="' . esc_url($url) . '">' . "\n";
                }
            }
        } catch (\Exception $e) {
            error_log('Enqueue::outputPreloadLinks error: ' . $e->getMessage());
            return;
        }
    }
}

/**
 * Encapsulates Vite dev/prod enqueue logic.
 */
class Vite
{
    /**
     * Get preload URLs for the given path.
     */
    public static function getPreloadUrls(string $path): array
    {
        $manifest = self::getManifest();
        if (!$manifest) {
            return [];
        }

        $key = self::getRouteKey($path);
        if (!$key || !isset($manifest[$key])) {
            return [];
        }

        $urls = [];
        $dist_uri = '/wp-content/themes/' . get_stylesheet() . '/assets/dist';

        // Collect all transitive imports for main entry
        $entry_key = self::viteEntry();
        if ($entry_key) {
            $entry_urls = self::getAllTransitiveAssets($manifest, $entry_key);
            foreach ($entry_urls as $url) {
                $urls[] = $dist_uri . '/' . $url;
            }
        }

        // Collect all transitive imports for app.svelte
        $app_key = 'src/app.svelte';
        $app_urls = self::getAllTransitiveAssets($manifest, $app_key);
        foreach ($app_urls as $url) {
            $urls[] = $dist_uri . '/' . $url;
        }

        // Collect all transitive imports for route
        $route_urls = self::getAllTransitiveAssets($manifest, $key);
        foreach ($route_urls as $url) {
            $urls[] = $dist_uri . '/' . $url;
        }

        // Remove duplicates
        $urls = array_unique($urls);

        return $urls;
    }

    /**
     * Get all transitive assets (JS and CSS) for a given manifest key.
     */
    private static function getAllTransitiveAssets(array $manifest, string $key, array &$visited = []): array
    {
        if (in_array($key, $visited, true)) {
            return [];
        }
        $visited[] = $key;

        $assets = [];

        // Add the main file
        if (isset($manifest[$key]['file'])) {
            $assets[] = $manifest[$key]['file'];
        }

        // Add CSS
        if (isset($manifest[$key]['css'])) {
            $assets = array_merge($assets, $manifest[$key]['css']);
        }

        // Recursively add imports
        if (isset($manifest[$key]['imports'])) {
            foreach ($manifest[$key]['imports'] as $import) {
                $assets = array_merge($assets, self::getAllTransitiveAssets($manifest, $import, $visited));
            }
        }

        return $assets;
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
        $dist_uri = '/wp-content/themes/' . get_stylesheet() . '/assets/dist';

        $manifest = self::getManifest();
        if ($manifest === null) {
            return [];
        }
        $manifest_key = self::viteEntry();
        if (empty($manifest[$manifest_key]['file'])) {
            return [];
        }

        $main_js = $manifest[$manifest_key]['file'];

        $svelte_handle = 'svelte-boot';
        wp_enqueue_script_module(
            $svelte_handle,
            $dist_uri . '/' . $main_js,
            [],
            null
        );
        return [];
    }
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

    /**
     * Get the route key based on the path.
     */
    private static function getRouteKey(string $path): ?string
    {
        if ($path === '/' || $path === '') {
            return 'src/app/routes/Homepage.svelte';
        }
        if (strpos($path, '/pasang-iklan-loker') === 0) {
            return 'src/app/routes/PasangIklanLoker.svelte';
        }
        if (preg_match('/^\/lowongan\//', $path)) {
            return 'src/app/routes/SingleLowongan.svelte';
        }
        return null;
    }
}