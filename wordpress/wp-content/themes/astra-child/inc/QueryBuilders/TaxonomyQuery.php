<?php

namespace AstraChild\QueryBuilders;

/**
 * Encapsulates taxonomy-related query construction.
 */
class TaxonomyQuery
{
    /**
     * Build tax_query parts for job search based on incoming params.
     * Returns an array of tax_query fragments (not wrapped with relation).
     *
     * @param array $params
     * @return array
     */
    public static function jobTaxQueryParts(array $params): array
    {
        $tax_query = [];

        if (!empty($params['lokasi'])) {
            $lokasi_terms = is_array($params['lokasi'])
                ? array_map('sanitize_text_field', $params['lokasi'])
                : [sanitize_text_field($params['lokasi'])];
            $tax_query[] = [
                'taxonomy' => 'lokasi-pekerjaan',
                'field' => 'slug',
                'terms' => $lokasi_terms,
                'operator' => 'IN',
            ];
        }

        if (!empty($params['gender'])) {
            $gender_terms = is_array($params['gender'])
                ? array_map('sanitize_text_field', $params['gender'])
                : [sanitize_text_field($params['gender'])];
            $tax_query[] = [
                'taxonomy' => 'gender',
                'field' => 'slug',
                'terms' => $gender_terms,
                'operator' => 'IN',
            ];
        }

        if (!empty($params['pendidikan'])) {
            $pendidikan_terms = is_array($params['pendidikan'])
                ? array_map('sanitize_text_field', $params['pendidikan'])
                : [sanitize_text_field($params['pendidikan'])];
            $tax_query[] = [
                'taxonomy' => 'pendidikan',
                'field' => 'slug',
                'terms' => $pendidikan_terms,
                'operator' => 'IN',
            ];
        }

        // perusahaan handled specially when 'cari' (search) is provided
        if (!empty($params['cari'])) {
            $search_term = sanitize_text_field($params['cari']);
            $tax_query[] = [
                'taxonomy' => 'perusahaan',
                'field'    => 'name',
                'terms'    => $search_term,
                'operator' => 'LIKE',
            ];
        }

        return $tax_query;
    }

    /**
     * Return args to fetch all terms (ids) for a taxonomy (used for cleanup or listing unused terms).
     *
     * @param string $taxonomy
     * @return array
     */
    public static function unusedTaxonomiesTermsArgs(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);
        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }
        return $terms;
    }
}
