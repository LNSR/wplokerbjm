<?php

namespace AstraChild\Models;
use AstraChild\Helpers\TaxonomyHelpers;

/**
 * Taxonomy Model
 * 
 * Handles taxonomy operations for job listings
 */
class TaxonomyModel
{
    /**
     * Available taxonomies
     *
     * @var array
     */
    protected $taxonomies = [
        'kategori-lowongan' => 'Kategori Pekerjaan',
        'lokasi-pekerjaan' => 'Lokasi Pekerjaan',
        'jenis-pekerjaan' => 'Jenis Pekerjaan',
        'gender' => 'Gender',
        'pendidikan' => 'Pendidikan',
        'pengalaman' => 'Pengalaman',
        'gaji' => 'Gaji',
        'usia' => 'Usia'
    ];

    /**
     * Get terms for a specific taxonomy
     *
     * @param string $taxonomy Taxonomy name
     * @param array $args Additional arguments for get_terms()
     * @return array Array of term objects
     */
    public function getTerms($taxonomy, array $args = [])
    {
        if (!in_array($taxonomy, array_keys($this->taxonomies))) {
            return [];
        }

        $default_args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ];

        $args = wp_parse_args($args, $default_args);
        $terms = get_terms($args);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        return $terms;
    }

    /**
     * Get all available job-related taxonomies
     *
     * @return array Associative array of taxonomy names and labels
     */
    public function getAvailableTaxonomies()
    {
        return $this->taxonomies;
    }

    /**
     * Get all filter data for job search
     *
     * @return array Array of taxonomy terms for filters
     */
    public function getFilterData()
    {
        return [
            'locations' => $this->getTerms('lokasi-pekerjaan'),
            'job_types' => $this->getTerms('jenis-pekerjaan'),
            'genders' => $this->getTerms('gender'),
            'education' => $this->getTerms('pendidikan'),
            'experiences' => $this->getTerms('pengalaman'),
            'salaries' => $this->getTerms('gaji'),
            'ages' => $this->getTerms('usia')
        ];
    }

    /**
     * Get terms for a post
     *
     * @param int $post_id Post ID
     * @param string $taxonomy Taxonomy name
     * @return array Terms associated with the post
     */
    public function getPostTerms($post_id, $taxonomy)
    {
        if (!in_array($taxonomy, array_keys($this->taxonomies))) {
            return [];
        }

        $terms = get_the_terms($post_id, $taxonomy);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        return $terms;
    }

    /**
     * Set taxonomy terms for a post
     * 
     * @param int $post_id Post ID
     * @param array|int $term_ids Term ID or array of term IDs
     * @param string $taxonomy Taxonomy name
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function setPostTerms(int $post_id, $term_ids, string $taxonomy): bool
    {
        return !is_wp_error(wp_set_post_terms($post_id, $term_ids, $taxonomy));
    }

    /**
     * Create term if it doesn't exist
     * 
     * @param string $term_name Term name
     * @param string $taxonomy Taxonomy name
     * @return int|WP_Error Term ID on success, WP_Error on failure
     */
    public function createTermIfNotExists(string $term_name, string $taxonomy)
    {
        $term = term_exists($term_name, $taxonomy);
        if (!$term) {
            $term = wp_insert_term($term_name, $taxonomy);
        }
        
        if (is_wp_error($term)) {
            return $term;
        }
        
        return is_array($term) ? $term['term_id'] : $term;
    }
}
