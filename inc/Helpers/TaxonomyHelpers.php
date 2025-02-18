<?php
namespace AstraChild\Helpers;

/**
 * Taxonomy Helpers
 * 
 * Static helpers for taxonomy operations
 */
class TaxonomyHelpers
{
    /**
     * Format taxonomy terms array to comma-separated string
     * 
     * @param array $terms Array of term objects
     * @return string Comma-separated string of term names
     */
    public static function formatTermsToString($terms): string
    {
        if (empty($terms)) {
            return '';
        }
        
        $names = array_map(function($term) {
            return $term->name;
        }, $terms);
        
        return implode(', ', $names);
    }

    /**
     * Get parameter to taxonomy mapping
     * 
     * @return array
     */
    public static function getParamToTaxonomyMapping(): array
    {
        return [
            'loc' => 'lokasi-pekerjaan',
            'jenis' => 'jenis-pekerjaan',
            'gender' => 'gender',
            'pendidikan' => 'pendidikan',
            'pengalaman' => 'pengalaman',
            'gaji' => 'gaji',
            'usia' => 'usia',
            'kategori' => 'category'
        ];
    }
    
    /**
     * Format taxonomy term for display
     * 
     * @param object $term WP_Term object
     * @param bool $include_count Whether to include post count
     * @return string Formatted term name
     */
    public static function formatTermForDisplay($term, bool $include_count = false): string
    {
        if (!$term) return '';
        
        return $include_count 
            ? sprintf('%s (%d)', $term->name, $term->count)
            : $term->name;
    }
    
    /**
     * Check if a taxonomy is hierarchical
     * 
     * @param string $taxonomy Taxonomy name
     * @return bool
     */
    public static function isHierarchicalTaxonomy(string $taxonomy): bool
    {
        return is_taxonomy_hierarchical($taxonomy);
    }

    /**
     * Build taxonomy query parameter from term object or ID
     *
     * @param mixed $term Term object, ID or slug
     * @param string $taxonomy Taxonomy name
     * @return array Query parameter
     */
    public static function buildTermQueryParam($term, string $taxonomy): array
    {
        if (is_object($term)) {
            return [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $term->term_id
            ];
        } elseif (is_numeric($term)) {
            return [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $term
            ];
        } else {
            return [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $term
            ];
        }
    }

    /**
     * Map taxonomy name to URL parameter
     *
     * @param string $taxonomy Taxonomy name
     * @return string|null Parameter name or null if not mapped
     */
    public static function mapTaxonomyToParam(string $taxonomy): ?string 
    {
        $map = [
            'lokasi-pekerjaan' => 'loc',
            'jenis-pekerjaan' => 'jenis',
            'gender' => 'gender',
            'pendidikan' => 'pendidikan',
            'pengalaman' => 'pengalaman',
            'gaji' => 'gaji',
            'usia' => 'usia',
            'category' => 'kategori'
        ];
        
        return $map[$taxonomy] ?? null;
    }
    
    /**
     * Map URL parameter to taxonomy name
     *
     * @param string $param URL parameter
     * @return string|null Taxonomy name or null if not mapped
     */
    public static function mapParamToTaxonomy(string $param): ?string
    {
        $map = array_flip(self::mapTaxonomyToParam);
        return $map[$param] ?? null;
    }
}