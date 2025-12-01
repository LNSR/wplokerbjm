<?php
namespace WPLokerBJM\Services\Taxonomy;

use WPLokerBJM\QueryBuilders\TaxonomyQuery;

class TaxonomyManagement
{
    /**
     * Delete unused terms from all taxonomies.
     * A term is considered unused if it hasn't been associated with any posts for over 3 months.
     */
    public static function deleteUnusedTerms()
    {
        $taxonomies = get_taxonomies([], 'names');
        foreach ($taxonomies as $taxonomy) {
            $terms = TaxonomyQuery::allTaxonomiesTermsArgs($taxonomy);
            foreach ($terms as $term_id) {
                $last_used = get_term_meta($term_id, 'last_used', true);
                if ($last_used && $last_used < strtotime('-3 months')) {
                    wp_delete_term($term_id, $taxonomy);
                }
            }
        }
    }
}