<?php

namespace WPLokerBJM\QueryBuilders;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};

/**
 * Encapsulates taxonomy-related query construction.
 *
 * @phpstan-import-type SearchFilters from \WPLokerBJM\QueryBuilders\JobQuery
 */
class TaxonomyQuery
{
    /**
     * Build tax_query parts for job search based on incoming params.
     * Returns an array of tax_query fragments (not wrapped with relation).
     *
     * @param SearchFilters $params
     * @return list<array{taxonomy: string, field: 'slug', terms: list<string>, operator: 'IN', include_children: bool}>
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
                'include_children' => is_taxonomy_hierarchical(Taxonomies::LOKASI_PEKERJAAN),
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
                'include_children' => is_taxonomy_hierarchical(Taxonomies::GENDER),
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
                'include_children' => is_taxonomy_hierarchical(Taxonomies::PENDIDIKAN),
            ];
        }

        return $tax_query;
    }
}
