<?php
namespace AstraChild\Controllers;

use AstraChild\Models\JobModel;
use AstraChild\Models\JobEntity;
use AstraChild\Views\Jobs\Single;

class JobController {
    /**
     * @var JobModel
     */
    private $model;
    
    /**
     * Initialize the controller
     */
    public function __construct() {
        $this->model = new JobModel();
        $this->registerHooks();
    }
    
    /**
     * Register WordPress hooks
     */
    private function registerHooks() {
        // Single job template hooks
        add_action('wp', [$this, 'setupSingleJob']);
        
        // Archive page hooks
        add_action('pre_get_posts', [$this, 'modifyJobArchiveQuery']);
        
        // Template filters
        add_filter('template_include', [$this, 'maybeLoadCustomTemplate']);
    }
    
    /**
     * Setup data for single job view
     */
    public function setupSingleJob() {
        if (is_singular('lowongan')) {
            $post_id = get_the_ID();
            $job_entity = $this->model->createJobEntity($post_id);
            
            // Make job entity available to templates
            set_query_var('job_entity', $job_entity);
            
            // Update view count
            $this->incrementViewCount($post_id);
        }
    }
    
    /**
     * Modify the main query for job archive pages
     * 
     * @param \WP_Query $query The main query object
     */
    public function modifyJobArchiveQuery($query) {
        // Only modify main query for job archives
        if (!is_admin() && $query->is_main_query() && 
            ($query->is_post_type_archive('lowongan') || 
             $query->is_tax(array_keys($this->model->getTaxonomyModel()->getAvailableTaxonomies())))) {
            
            $query->set('posts_per_page', 12);
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        }
    }
    
    /**
     * Check if we should load a custom template
     * 
     * @param string $template The template path
     * @return string Modified template path if needed
     */
    public function maybeLoadCustomTemplate($template) {
        // Add custom template logic if needed
        return $template;
    }
    
    /**
     * Get job data for display in templates
     * 
     * @param int $post_id The job post ID
     * @return array|null Job data or null if not found
     */
    public function getJobData($post_id) {
        return $this->model->getJob($post_id);
    }
    
    /**
     * Get job entity for display in templates
     * 
     * @param int $post_id The job post ID
     * @return JobEntity|null Job entity or null if not found
     */
    public function getJobEntity($post_id) {
        return $this->model->createJobEntity($post_id);
    }
    
    /**
     * Get job filters for search form
     * 
     * @return array Filter data for the search form
     */
    public function getJobFilters() {
        return $this->model->getTaxonomyModel()->getFilterData();
    }
    
    /**
     * Get recent jobs for sidebar or widgets
     * 
     * @param int $count Number of jobs to get
     * @return array Recent jobs data
     */
    public function getRecentJobs($count = 5) {
        return $this->model->getRecentJobs($count);
    }
    
    /**
     * Increment the view count for a job post
     * 
     * @param int $post_id The job post ID
     * @return void
     */
    private function incrementViewCount($post_id) {
        // Get current views
        $views = get_post_meta($post_id, 'job_view_count', true);
        $views = empty($views) ? 0 : intval($views);
        
        // Increment and update
        update_post_meta($post_id, 'job_view_count', $views + 1);
    }
    
    /**
     * Save a job post
     * 
     * @param array $data Job data
     * @param int $post_id Optional post ID for updates
     * @return int|false Post ID on success, false on failure
     */
    public function saveJob(array $data, $post_id = null) {
        $entity = new JobEntity();
        
        if ($post_id) {
            $entity->setAttribute('ID', $post_id);
        }
        
        $entity->setAttributes($data);
        
        if ($entity->save()) {
            return $entity->getAttribute('ID');
        }
        
        return false;
    }

    /**
     * Get search results jobs from request parameters
     * 
     * @return array Search results data
     */
    public function getSearchResultsJobs(): array
    {
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        return $this->model->getSearchResultsJobs($paged);
    }

    public function getJobStatusAttributes($status) {
        return $this->model->getJobStatusAttributes($status);
    }
}
