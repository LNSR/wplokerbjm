<?php

namespace AstraChild\Services\Taxonomy;

class TaxonomyService {
    /**
     * Process taxonomy terms.
     *
     * @param array|false|\WP_Error|null $terms Raw taxonomy terms.
     * @return array Processed taxonomy term names.
     */
    public function processTaxonomyTerms($terms): array {
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        return array_map(fn($term) => sanitize_text_field($term->name), $terms);
    }

    /**
     * Build a tree structure from flat taxonomy terms.
     *
     * @param array $terms Flat array of taxonomy terms.
     * @return array Hierarchical tree of taxonomy terms.
     */
    public function buildTermsTree($terms): array
    {
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
        return $tree;
    }
}