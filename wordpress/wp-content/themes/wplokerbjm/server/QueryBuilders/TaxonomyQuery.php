<?php

namespace WPLokerBJM\QueryBuilders;
use WPLokerBJM\Models\Schema\Taxonomies;

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

        if (!empty($params[Taxonomies::LOKASI_PEKERJAAN])) {
            $lokasi_terms = is_array($params[Taxonomies::LOKASI_PEKERJAAN])
                ? array_map('sanitize_text_field', $params[Taxonomies::LOKASI_PEKERJAAN])
                : [sanitize_text_field($params[Taxonomies::LOKASI_PEKERJAAN])];
            $tax_query[] = [
                'taxonomy' => Taxonomies::LOKASI_PEKERJAAN,
                'field' => 'slug',
                'terms' => $lokasi_terms,
                'operator' => 'IN',
            ];
        }

        if (!empty($params[Taxonomies::GENDER])) {
            $gender_terms = is_array($params[Taxonomies::GENDER])
                ? array_map('sanitize_text_field', $params[Taxonomies::GENDER])
                : [sanitize_text_field($params[Taxonomies::GENDER])];
            $tax_query[] = [
                'taxonomy' => Taxonomies::GENDER,
                'field' => 'slug',
                'terms' => $gender_terms,
                'operator' => 'IN',
            ];
        }

        if (!empty($params[Taxonomies::PENDIDIKAN])) {
            $pendidikan_terms = is_array($params[Taxonomies::PENDIDIKAN])
                ? array_map('sanitize_text_field', $params[Taxonomies::PENDIDIKAN])
                : [sanitize_text_field($params[Taxonomies::PENDIDIKAN])];
            $tax_query[] = [
                'taxonomy' => Taxonomies::PENDIDIKAN,
                'field' => 'slug',
                'terms' => $pendidikan_terms,
                'operator' => 'IN',
            ];
        }

        // perusahaan handled specially when 'cari' (search) is provided
        if (!empty($params['cari'])) {
            $search_term = sanitize_text_field($params['cari']);
            $tax_query[] = [
                'taxonomy' => Taxonomies::PERUSAHAAN,
                'field' => 'name',
                'terms' => $search_term,
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
    public static function allTaxonomiesTermsArgs(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'ids',
        ]);
        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }
        return $terms;
    }
}
