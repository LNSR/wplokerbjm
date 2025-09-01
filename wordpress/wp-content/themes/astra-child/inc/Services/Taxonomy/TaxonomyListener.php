<?php
namespace AstraChild\Services\Taxonomy;
use AstraChild\Contracts\HooksInterface;
use AstraChild\Core\Cache;

/**
 * Listens to taxonomy term changes and clears relevant transients.
 */
class TaxonomyListener implements HooksInterface
{
    public function registerActions(): void
    {
        add_action('edited_term', [$this, 'onTermChange'], 20, 3);
        add_action('created_term', [$this, 'onTermChange'], 20, 3);
        add_action('delete_term', [$this, 'onTermChange'], 20, 3);
    }

    public function registerFilters(): void
    {
        // Register filters here
    }

    /**
     * Handle term changes to clear transients.
     *
     * @param int $term_id Term ID.
     * @param int $tt_id Term taxonomy ID.
     * @param string $taxonomy Taxonomy name.
     */
    public function onTermChange($term_id, $tt_id, $taxonomy): void
    {

        // Clear global taxonomy caches
        Cache::delete('taxonomy_depth_all');
        if (in_array($taxonomy, ['lokasi', 'gender', 'pendidikan'])) {
            Cache::delete('taxonomy_depth_' . $taxonomy);
        }
        Cache::delete('taxonomy_terms_all');
        Cache::delete('taxonomy_tree_' . $taxonomy);
    }
}