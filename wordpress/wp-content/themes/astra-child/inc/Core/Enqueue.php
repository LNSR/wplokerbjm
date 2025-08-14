<?php

namespace AstraChild\Core;
use AstraChild\Contracts\HooksInterface;

class Enqueue implements HooksInterface
{

    public function __construct(private \AstraChild\Core\Enqueue\Vite $vite)
    {
    }
    private array $noOptimizeStyleHandles = [];

    /**
     * Register scripts and styles.
     */
    public function registerActions(): void
    {
    add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 2);
    }

    /**
     * Register filters used by this class.
     */
    public function registerFilters(): void
    {
        add_filter('style_loader_tag', [$this, 'filterStyleLoaderTag'], 2, 2);
    }

    public function enqueueAssets(): void
    {

        if ($this->vite->isDevelopment()) {
            $this->vite->enqueueForDevelopment();
            return;
        }

        $prod = $this->vite->enqueueForProduction();
        if (empty($prod)) {
            return;
        }

    $this->noOptimizeStyleHandles = array_merge($this->noOptimizeStyleHandles, $prod['noOptimizeStyleHandles'] ?? []);
    }

    /**
     * Filter callback for style_loader_tag.
     * Adds data-no-optimize attribute to specific styles.
     */
    public function filterStyleLoaderTag(string $tag, string $handle): string
    {
        if (in_array($handle, $this->noOptimizeStyleHandles, true)) {
            return str_replace('<link ', '<link data-no-optimize="1" ', $tag);
        }
        return $tag;
    }
}
