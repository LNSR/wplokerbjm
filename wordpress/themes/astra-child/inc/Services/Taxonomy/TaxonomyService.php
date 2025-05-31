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
}