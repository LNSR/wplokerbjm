<?php
namespace WPLokerBJM\Core\Cron\Taxonomy;

use WPLokerBJM\QueryBuilders\TaxonomyQuery;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Cron\WPCron;
use WPLokerBJM\Shared\Log\Logger;

class TaxonomyManagement
{
    /**
     * Delete unused terms from all taxonomies.
     * A term is considered unused if it hasn't been associated with any posts for over 3 months.
     */
    #[Action(WPCron::CLEANUP_TAXONOMY)]
    public function deleteUnusedTerms()
    {
        $taxonomies = get_taxonomies([], 'names');
        foreach ($taxonomies as $taxonomy) {
            $terms = TaxonomyQuery::allTaxonomiesTerms($taxonomy, 'ids');
            foreach ($terms as $term_id) {
                $last_used = get_term_meta($term_id, 'last_used', true);
                if ($last_used && $last_used < strtotime('-3 months')) {
                    $term = get_term($term_id, $taxonomy);
                    Logger::warning("TaxonomyManagement", "Deleting term: " . $term->name);
                    wp_delete_term($term_id, $taxonomy);
                }
            }
        }
    }
}