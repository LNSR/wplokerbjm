<?php

namespace WPLokerBJM\QueryBuilders;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};

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

    /**
     * Get the last modified date for taxonomies by finding the most recent modification
     * of posts that have terms in the job-related taxonomies.
     *
     * @return string The GMT modified date of the latest post with taxonomy terms, or current GMT time if none exist.
     */
    public static function getLastModifiedDateForTaxonomies(): string
    {
        $cache_key = CacheKey::TAXONOMY_LAST_MODIFIED;
        $cached = Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $taxonomies = [Taxonomies::LOKASI_PEKERJAAN, Taxonomies::GENDER, Taxonomies::PENDIDIKAN];
        $placeholders = implode(',', array_fill(0, count($taxonomies), '%s'));
        $query = $wpdb->prepare(
            "
            SELECT MAX(p.post_modified_gmt) as last_modified
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE p.post_type = %s AND tt.taxonomy IN ($placeholders)
        ",
            array_merge([PostTypes::POST_TYPE_LOWONGAN], $taxonomies)
        );
        $result = $wpdb->get_var($query);
        $final_result = $result ?: gmdate('c');

        Cache::set($cache_key, $final_result, 86400); // Cache for 1 day
        return $final_result;
    }

    public static function getTaxonomyOptions(array $taxonomies): array
    {
        $options = [];

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ]);

            if (is_wp_error($terms) || !is_array($terms)) {
                $options[$taxonomy] = [];
                continue;
            }

            $options[$taxonomy] = array_values(array_map(
                fn($term): array => [
                    'id' => (int) $term->term_id,
                    'name' => html_entity_decode((string) $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'slug' => (string) $term->slug,
                    'parent' => isset($term->parent) ? (int) $term->parent : 0,
                ],
                $terms
            ));
        }

        return $options;
    }
}
