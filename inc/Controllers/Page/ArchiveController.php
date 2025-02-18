<?php
namespace AstraChild\Controllers\Page;

use AstraChild\Models\JobModel;
use AstraChild\Models\TaxonomyModel;
use AstraChild\Controllers\TaxonomyController;

class ArchiveController {
    /**
     * @var JobModel
     */
    protected $jobModel;
    
    /**
     * @var TaxonomyModel
     */
    protected $taxonomyModel;
    /**
     * @var TaxonomyController
     */
    protected $taxonomyController;
    
    /**
     * Initialize controller
     */
    public function __construct() {
        $this->jobModel = new JobModel();
        $this->taxonomyModel = new TaxonomyModel();
        $this->taxonomyController = new TaxonomyController();
    }
    
    /**
     * Get filter options for the archive page
     *
     * @return array
     */
    public function getFilterOptions(): array {
        return $this->taxonomyController->getHierarchicalFilterOptions(); 
    }
    
    /**
     * Get current page data
     *
     * @return array
     */
    public function getPageData(): array {
        global $wp_query;
        
        return [
            'current_page' => max(1, get_query_var('paged')),
            'max_pages' => $wp_query->max_num_pages,
            'found_posts' => $wp_query->found_posts
        ];
    }
    
    /**
     * Get active filters for display
     *
     * @return array
     */
    public function getActiveFilters(): array
    {
        $filter_options = $this->getFilterOptions();
        $active_filters = [];
        
        foreach ($filter_options as $param => $config) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $value = sanitize_text_field($_GET[$param]);
                
                // For flat taxonomies
                if ($config['type'] === 'flat' && !empty($config['terms'][$value])) {
                    $active_filters[$config['label']] = $config['terms'][$value];
                    continue;
                }
                
                // For hierarchical taxonomies
                if ($config['type'] === 'hierarchical') {
                    // Find the term in the hierarchical structure
                    $term_name = $this->findTermNameBySlug($config['terms'], $value);
                    if ($term_name) {
                        $active_filters[$config['label']] = $term_name;
                    }
                }
            }
        }
        
        return $active_filters;
    }

    /**
     * Find term name by slug in hierarchical structure
     *
     * @param array $terms Hierarchical terms
     * @param string $slug Term slug to find
     * @return string|null Term name if found, null otherwise
     */
    private function findTermNameBySlug(array $terms, string $slug): ?string
    {
        foreach ($terms as $term_data) {
            if (isset($term_data['term']) && $term_data['term']->slug === $slug) {
                return $term_data['term']->name;
            }
            
            if (!empty($term_data['children'])) {
                $name = $this->findTermNameBySlug($term_data['children'], $slug);
                if ($name) {
                    return $name;
                }
            }
        }
        
        return null;
    }
}