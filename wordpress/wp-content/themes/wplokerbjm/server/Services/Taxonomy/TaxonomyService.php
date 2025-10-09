<?php

namespace WPLokerBJM\Services\Taxonomy;

class TaxonomyService
{

    /**
     * Process taxonomy terms.
     *
     * @param array $terms Raw taxonomy terms (array of term objects/arrays/strings).
     * @return array Processed taxonomy term names.
     */
    public function processTaxonomyTerms(array $terms): array
    {
        if (empty($terms)) {
            return [];
        }

        $names = [];
        foreach ($terms as $term) {
            if ($term instanceof \WP_Term && isset($term->name)) {
                $names[] = sanitize_text_field($term->name);
                continue;
            }
            if (is_array($term) && isset($term['name'])) {
                $names[] = sanitize_text_field($term['name']);
                continue;
            }
            if (is_string($term) && $term !== '') {
                $names[] = sanitize_text_field($term);
            }
        }

        return $names;
    }

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
                    'children' => []
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