<?php
namespace AstraChild\Models;

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
}