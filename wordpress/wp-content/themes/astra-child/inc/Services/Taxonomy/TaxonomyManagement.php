<?php
namespace AstraChild\Services\Taxonomy;

use AstraChild\Contracts\HooksInterface;
use AstraChild\QueryBuilders\JobQuery;

class TaxonomyManagement implements HooksInterface
{
    public function registerActions(): void
    {
        add_action('astra_child_cleanup_taxonomy', [$this, 'deleteUnusedTermsCron']);

        if (!wp_next_scheduled('astra_child_cleanup_taxonomy')) {
            wp_schedule_event(time(), 'weekly', 'astra_child_cleanup_taxonomy');
        }
    }

    public function registerFilters(): void
    {
        // No filters needed for cleanup
    }

    public function deleteUnusedTermsCron()
    {
        $taxonomies = get_taxonomies([], 'names');
        foreach ($taxonomies as $taxonomy) {
            $this->deleteUnusedTerms($taxonomy);
        }
    }

    /**
     * Delete taxonomy terms not used in the last 3 months.
     *
     * @param string $taxonomy
     */
    public function deleteUnusedTerms($taxonomy)
    {
        $terms = JobQuery::unusedTaxonomiesTermsArgs($taxonomy);

        foreach ($terms as $term_id) {
            $last_used = get_term_meta($term_id, 'last_used', true);
            if ($last_used && $last_used < strtotime('-3 months')) {
                wp_delete_term($term_id, $taxonomy);
            }
        }
    }
}