<?php
namespace AstraChild\Models\Schema;

/**
 * Schema Manager
 * 
 * Handles relationships between different schema components
 */
class SchemaManager 
{
    /**
     * Initialize the schema manager
     */
    public function __construct()
    {
        // Register hooks
        add_action('init', [$this, 'updateLowonganTaxonomies'], 11);
    }

    /**
     * Updates the 'lowongan' post type to include specified taxonomies
     * This function runs after the initial post type registration
     * 
     * @return void
     */
    public function updateLowonganTaxonomies(): void
    {
        $taxonomies = [
            'jenis-pekerjaan',    // Job Type
            'lokasi-pekerjaan',   // Job Location
            'kategori-lowongan',  // Job Category
            'gender',             // Gender Requirement
            'pendidikan',         // Education Requirement
            'pengalaman',         // Experience Requirement
            'gaji',               // Salary Range
            'usia'                // Age Requirement
        ];
        
        // Update the post type registration with new taxonomies
        global $wp_post_types;
        if (isset($wp_post_types['lowongan'])) {
            $wp_post_types['lowongan']->taxonomies = $taxonomies;
        }
    }

    /**
     * Get configuration of job taxonomies
     * 
     * @return array Taxonomy configuration
     */
    public function getJobTaxonomyConfiguration(): array
    {
        return [
            'jenis-pekerjaan' => [
                'label' => 'Jenis Pekerjaan',
                'hierarchical' => false,
                'param' => 'jenis'
            ],
            'lokasi-pekerjaan' => [
                'label' => 'Lokasi Pekerjaan',
                'hierarchical' => true,
                'param' => 'loc'
            ],
            'kategori-lowongan' => [
                'label' => 'Kategori Pekerjaan',
                'hierarchical' => true,
                'param' => 'kategori'
            ],
            'gender' => [
                'label' => 'Gender',
                'hierarchical' => false,
                'param' => 'gender'
            ],
            'pendidikan' => [
                'label' => 'Pendidikan',
                'hierarchical' => true,
                'param' => 'pendidikan'
            ],
            'pengalaman' => [
                'label' => 'Pengalaman',
                'hierarchical' => false,
                'param' => 'pengalaman'
            ],
            'gaji' => [
                'label' => 'Gaji',
                'hierarchical' => false,
                'param' => 'gaji'
            ],
            'usia' => [
                'label' => 'Usia',
                'hierarchical' => false,
                'param' => 'usia'
            ]
        ];
    }
}