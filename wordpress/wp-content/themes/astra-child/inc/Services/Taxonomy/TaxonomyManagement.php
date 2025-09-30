<?php
namespace AstraChild\Services\Taxonomy;

use AstraChild\QueryBuilders\TaxonomyQuery;

class TaxonomyManagement
{
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
        $terms = TaxonomyQuery::unusedTaxonomiesTermsArgs($taxonomy);

        foreach ($terms as $term_id) {
            $last_used = get_term_meta($term_id, 'last_used', true);
            if ($last_used && $last_used < strtotime('-3 months')) {
                wp_delete_term($term_id, $taxonomy);
            }
        }
    }
}