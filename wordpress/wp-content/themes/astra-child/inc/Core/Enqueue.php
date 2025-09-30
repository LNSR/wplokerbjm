<?php

namespace AstraChild\Core;
use AstraChild\Contracts\HooksInterface;
use AstraChild\Core\Enqueue\Vite;

class Enqueue implements HooksInterface
{

    /**
     * Register scripts and styles.
     */
    public function registerActions(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Register filters used by this class.
     */
    public function registerFilters(): void
    {
        add_filter('style_loader_tag', [$this, 'filterStyleLoaderTag'], 10, 2);
    }

    public function enqueueAssets(): void
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
    public function filterStyleLoaderTag(string $tag, string $handle): string
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
