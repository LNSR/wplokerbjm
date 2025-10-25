<?php

namespace WPLokerBJM\Services\Taxonomy;

class TaxonomyService
{
    /**
     * Build a tree structure from flat taxonomy terms.
     *
     * @param array $terms Flat array of taxonomy terms.
     * @param string $taxonomy Taxonomy name for caching.
     * @return array Hierarchical tree of taxonomy terms.
     */
    public function buildTermsTree(array $terms, $taxonomy = ''): array
    {
        try {

            $terms_by_id = [];
            foreach ($terms as $term) {
                $terms_by_id[$term->term_id] = [
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'parent' => $term->parent,
                    'children' => [],
                ];
            }
            $tree = [];
            foreach ($terms_by_id as &$term) {
                if ($term['parent'] && isset($terms_by_id[$term['parent']])) {
                    $terms_by_id[$term['parent']]['children'][] = &$term;
                } else {
                    $tree[] = &$term;
                }
            }
            unset($term);

            return $tree;
        } catch (\Exception $e) {
            error_log('TaxonomyService::buildTermsTree error: ' . $e->getMessage());
            return [];
        }
    }
}