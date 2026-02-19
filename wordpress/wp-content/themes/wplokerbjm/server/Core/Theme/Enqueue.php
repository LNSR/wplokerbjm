<?php

namespace WPLokerBJM\Core\Theme;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};

class Enqueue
{
    #[Action('wp_enqueue_scripts', 0)]
    public static function enqueueAssets(): void
    {
        try {
            if (SharedUtils::isDevelopment()) {
                Vite::enqueueForDevelopment();
                return;
            }

            $prod = Vite::enqueueForProduction();
            if (empty($prod)) {
                return;
            }
            // No need to merge, just use the static property in filterStyleLoaderTag
        } catch (\Exception $e) {
            Logger::error('Enqueue', 'Enqueue::enqueueAssets error: ' . $e->getMessage());
        }
    }

    #[Action('wp_head', 30)]
    public static function outputViteCSSAssets(): void
    {
        try {
            if (SharedUtils::isDevelopment()) {
                return;
            }

            $urls = Vite::getPreloadUrls($_SERVER['REQUEST_URI'] ?? '/');
            foreach ($urls as $url) {
                if (str_ends_with($url, '.css')) {
                    echo '<link rel="stylesheet" crossorigin href="' . esc_url($url) . '">' . "\n";
                }
            }
        } catch (\Exception $e) {
            Logger::error('Enqueue', 'Enqueue::outputViteCSSAssets error: ' . $e->getMessage());
            return;
        }
    }

    /**
     * Send HTTP Link headers for route-specific JS and CSS assets.
     */
    public static function outputViteAssetsPreloadLinksResponse(): void
    {
        try {
            if (SharedUtils::isDevelopment() || SharedUtils::isLocalhost()) {
                return;
            }

            // Only send headers if not already sent
            if (headers_sent()) {
                Logger::warning('Enqueue', 'Headers already sent, skipping preload Link headers');
                return;
            }

            $path = $_SERVER['REQUEST_URI'] ?? '/';

            // Try to serve cached consolidated Link header first. TTL matches manifest/urls cache.
            $device = wp_is_mobile() ? 'mobile' : 'desktop';
            $cacheKey = CacheKey::PRELOAD_LINK_HEADER_PREFIX . $device . '_' . md5($path);
            $cachedHeader = Cache::get($cacheKey);
            if ($cachedHeader !== false) {
                if (!self::isLinkHeaderAlreadySet($cachedHeader)) {
                    header("Link: {$cachedHeader}", false);
                }
                return;
            }

            $urls = Vite::getPreloadUrls($path);

            $linkParts = [];
            foreach ($urls as $url) {
                // Use raw escaping for header values
                $safe = esc_url_raw($url);
                if (str_ends_with($url, '.js')) {
                    $linkParts[] = "<{$safe}>; rel=modulepreload; as=script; crossorigin";
                } elseif (str_ends_with($url, '.css')) {
                    $linkParts[] = "<{$safe}>; rel=preload; as=style; crossorigin";
                }
            }

            if (!empty($linkParts)) {
                $header = implode(', ', $linkParts);
                // Cache the consolidated header for 1 day to match manifest TTL
                Cache::set($cacheKey, $header, 86400);
                if (!self::isLinkHeaderAlreadySet($header)) {
                    header("Link: {$header}", false);
                }
            }
        } catch (\Exception $e) {
            Logger::error('Enqueue', 'Enqueue::outputViteAssetsPreloadLinksResponse error: ' . $e->getMessage());
            return;
        }
    }

    /**
     * Check if the Link header is already set with the given value.
     */
    private static function isLinkHeaderAlreadySet(string $value): bool
    {
        $headers = headers_list();
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Link: ')) {
                $existingValue = substr($header, 6);
                if ($existingValue === $value) {
                    return true;
                }
            }
        }
        return false;
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
        $device = wp_is_mobile() ? 'mobile' : 'desktop';
        $cacheKey = CacheKey::PRELOAD_URLS_PREFIX . $device . '_' . md5($path);
        $urls = Cache::get($cacheKey);
        if ($urls !== false) {
            return $urls;
        }

        $manifest = self::getManifest();
        if (!$manifest) {
            return [];
        }

        $key = self::getRouteKey($path);
        $keys = is_array($key) ? $key : ($key ? [$key] : []);
        if (empty($keys)) {
            return [];
        }

        $urls = [];
        $dist_uri = self::getDistUri();

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

        // Collect all transitive imports for route(s)
        $route_urls = [];
        foreach ($keys as $k) {
            if (!isset($manifest[$k])) {
                continue;
            }
            $route_urls = array_merge($route_urls, self::getAllTransitiveAssets($manifest, $k));
        }
        foreach ($route_urls as $url) {
            $urls[] = $dist_uri . '/' . $url;
        }

        // Remove duplicates
        $urls = array_unique($urls);

        Cache::set($cacheKey, $urls, 86400); // Cache for 1 day, matching manifest TTL
        return $urls;
    }

    /**
     * Enqueue scripts when running the Vite dev server.
     * No preload values are returned anymore.
     */
    public static function enqueueForDevelopment(): array
    {
        $vite_base_url = '/__vite';
        $vite_handle = 'vite-entry';
        $client_handle = 'vite-client';

        // Enqueue module scripts for dev server
        wp_enqueue_script_module(
            $vite_handle,
            "{$vite_base_url}/" . self::viteEntry(), // entry point are from manifest keys, so build first to generate manifest.json
            [],
            null,
            [
                'in_footer' => false,
                'fetchpriority' => 'high',
            ]
        );

        wp_enqueue_script_module(
            $client_handle,
            "{$vite_base_url}/@vite/client",
            [],
            null,
            [
                'in_footer' => false,
                'fetchpriority' => 'high',
            ]
        );

        return [];
    }

    /**
     * Enqueue built assets for production.
     * Returns array with keys: noOptimizeStyleHandles, assetVersion
     */
    public static function enqueueForProduction(): array
    {
        $dist_uri = self::getDistUri();

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
            null,
            [
                'in_footer' => false,
                'fetchpriority' => 'high',
            ]
        );
        return [];
    }

    /**
     * Get the route key based on the path.
     */
    private static function getRouteKey(string $path): string|array|null
    {
        $homepage = 'src/app/routes/Homepage.svelte';
        $singlelowongan = 'src/app/routes/SingleLowongan.svelte';
        if ($path === '/' || $path === '') {
            return $homepage;
        }
        if (strpos($path, '/pasang-iklan-loker') === 0) {
            return 'src/app/routes/PasangIklanLoker.svelte';
        }
        if (strpos($path, '/kebijakan-privasi') === 0) {
            return 'src/app/routes/KebijakanPrivasi.svelte';
        }
        if (preg_match('/^\/lowongan\//', $path)) {
            return wp_is_mobile() ? $singlelowongan : [$homepage, $singlelowongan]; // Desktop preloads both for sidepanel
        }
        return null;
    }


    private static function getDistUri(): string
    {
        if (defined('ABSPATH')) {
            return str_replace(ABSPATH, '/', self::getDistDir());
        }

        return '/wp-content/themes/' . get_stylesheet() . '/assets/dist';
    }

    private static function getDistDir(): string
    {
        return get_stylesheet_directory() . '/assets/dist';
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
        $dist_dir = self::getDistDir();
        $manifest_path = $dist_dir . '/.vite/manifest.json';

        $manifest = Cache::get(CacheKey::VITE_MANIFEST);
        if ($manifest === false) {
            if (!file_exists($manifest_path)) {
                return null;
            }
            $manifest = json_decode(file_get_contents($manifest_path), true);
            Cache::set(CacheKey::VITE_MANIFEST, $manifest, 86400); // Cache for 1 day
        }
        return $manifest;
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

        $cacheKey = CacheKey::TRANSITIVE_ASSETS_PREFIX . md5($key);
        $assets = Cache::get($cacheKey);
        if ($assets !== false) {
            return $assets;
        }

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

        Cache::set($cacheKey, $assets, 86400); // Cache for 1 day, matching manifest TTL
        return $assets;
    }
}