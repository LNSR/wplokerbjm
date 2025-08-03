<?php

namespace AstraChild\Services\Taxonomy;

class TaxonomyService
{
    /**
     * Process taxonomy terms.
     *
     * @param array|false|\WP_Error|null|string $terms Raw taxonomy terms.
     * @return array Processed taxonomy term names.
     */
    public function processTaxonomyTerms($terms): array
    {
        // Handle different input types
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        // If it's already a string, return it as an array element
        if (is_string($terms)) {
            return [sanitize_text_field($terms)];
        }

        // If it's not an array, try to convert or return empty
        if (!is_array($terms)) {
            return [];
        }

        // Process array of term objects
        return array_map(function ($term) {
            // Handle term objects
            if (is_object($term) && isset($term->name)) {
                return sanitize_text_field($term->name);
            }
            // Handle term arrays
            if (is_array($term) && isset($term['name'])) {
                return sanitize_text_field($term['name']);
            }
            // Handle strings
            if (is_string($term)) {
                return sanitize_text_field($term);
            }
            // Fallback for other types
            return sanitize_text_field((string) $term);
        }, $terms);
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