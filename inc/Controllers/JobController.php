<?php
namespace AstraChild\Controllers;

use AstraChild\Models\JobModel;
use AstraChild\Models\TaxonomyModel;
use AstraChild\Controllers\TaxonomyController;
use AstraChild\Models\JobEntity;
use AstraChild\Helpers\JobHelpers;

class JobController {
    /**
     * @var JobModel
     */
    private $model;
    /**
     * @var TaxonomyModel
     */
    private $taxonomyModel;
    
    /**
     * @var TaxonomyController
     */
    private $taxonomyController;
    
    /**
     * Initialize the controller with dependencies
     */
    public function __construct(
        ?JobModel $model = null,
        ?TaxonomyController $taxonomyController = null
    ) {
        $this->model = $model ?? new JobModel();
        $this->taxonomyController = $taxonomyController ?? new TaxonomyController();
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
     * Get a single job with all its data
     *
     * @param int $post_id Post ID
     * @return array|null Job data or null if not found
     */
    public function getJob($post_id)
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'lowongan') {
            return null;
        }

        $meta_data = $this->model->getJobMetaData($post_id);

        return [
            'id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'date' => $post->post_date,
            'permalink' => get_permalink($post_id),
            'meta' => $meta_data
        ];
    }

    /**
     * Get search results jobs from request parameters
     * 
     * @return array Search results data
     */
    public function getSearchResultsJobs(): array
    {
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        return $this->getJobs($paged);
    }
    /**
     * Get jobs by status for the carousel
     * 
     * @param string $status The status to filter by
     * @param int $limit Max number of posts to retrieve
     * @return array Job data formatted for carousel
     */
    public function getCarouselJobsByStatus($status, $limit = 10) 
    {
        // Your existing implementation but using model for data access
        $args = [
            'post_type' => 'lowongan',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => 'status_pekerjaan',
            'meta_value' => $status,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        // Use the model for data access only
        $results = $this->model->executeJobQuery($args);
        
        // Process the raw data into the required business format
        return $this->formatJobsForCarousel($results['query']);
    }
    
    /**
     * Format jobs for carousel display
     */
    private function formatJobsForCarousel($query)
    {
        $result = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $job_data = $this->model->getJobMetaData(get_the_ID());
                
                // Get status attributes - use JobHelpers
                $status_attrs = JobHelpers::getJobStatusAttributes($job_data['status']);
                
                // Format for display (business logic)
                $result[] = $this->formatSingleJobForCarousel($job_data, $status_attrs);
            }
            wp_reset_postdata();
        }
        
        return $result;
    }
    
    /**
     * Format a single job for carousel display
     */
    private function formatSingleJobForCarousel($job_data, $status_attrs)
    {
        // Business logic for formatting a job
        $border_class = !empty($status_attrs['class']) ?
            ' border-' . substr(strstr($status_attrs['class'], 'text-'), 5, -4) . '-200' : '';
        
        // Build job data structure - this is business logic!
        $job = [
            'id' => get_the_ID(),
            'title' => get_the_title(),
            'permalink' => get_permalink(),
            'company' => $job_data['company'],
            'location' => $job_data['location'],
            'education' => $job_data['education'],
            'experience' => $job_data['experience'],
            'status' => [
                'label' => $status_attrs['label'],
                'icon' => $status_attrs['icon'],
                'class' => $status_attrs['class'] . $border_class
            ]
        ];
        
        if (!empty($job_data['deadline'])) {
            $job['deadline'] = $job_data['deadline'];
        }
        
        return $job;
    }

    /**
     * Get jobs with flexible filtering - Moved business logic here!
     * 
     * @param int $paged Current page number
     * @param array $params Optional direct filter parameters
     * @return array Query results and pagination data
     */
    public function getFilteredJobs(int $paged = 1, array $params = []): array
    {
        // Build query args
        $args = $this->buildJobQueryArgs($paged);
        
        // Process filter parameters
        $merged_params = $this->processFilterParameters($params);
        
        // Add search term if provided
        if (!empty($merged_params['keywords'])) {
            $args['s'] = $merged_params['keywords'];
        }
        
        // Get taxonomy query from TaxonomyController
        $tax_query = $this->taxonomyController->buildTaxonomyQueryFromParams($merged_params);
        
        // Add taxonomy query if we have conditions
        if (!empty($tax_query)) {
            $args['tax_query'] = [
                'relation' => 'AND',
                $tax_query
            ];
        }
        
        // Execute query through model (pure data access)
        return $this->model->executeJobQuery($args);
    }
    
    /**
     * Process filter parameters from both direct params and GET
     */
    private function processFilterParameters(array $params): array
    {
        $merged_params = [];
        $filter_config = $this->taxonomyController->getFilterConfiguration();
        
        foreach ($filter_config as $param => $config) {
            // Skip inactive filters
            if (!$config['active']) {
                continue;
            }
            
            // Check either direct params or GET params
            $value = $params[$param] ?? ($_GET[$param] ?? null);
            
            if (!empty($value)) {
                $merged_params[$param] = sanitize_text_field($value);
            }
        }
        
        return $merged_params;
    }

    /**
     * Get featured jobs
     * 
     * @param int $page Current page number
     * @param array $filters Optional filter criteria
     * @param int $per_page Posts per page
     * @return array Query results and pagination data
     */
    public function getFeaturedJobs($page = 1, $filters = [], $per_page = 10)
    {
        $args = [
            'post_type' => 'lowongan',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'paged' => $page
        ];

        // Apply any filters here if needed
        // ...

        $query = new \WP_Query($args);

        return [
            'query' => $query,
            'max_pages' => $query->max_num_pages,
            'current_page' => $page,
            'found_posts' => $query->found_posts
        ];
    }
    
    /**
     * Build base job query arguments
     */
    private function buildJobQueryArgs(int $paged): array
    {
        return [
            'post_type' => 'lowongan',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'paged' => $paged
        ];
    }

    /**
     * Get jobs by status for carousel
     * 
     * @return array Array of jobs for status carousel
     */
    public function getStatusCarouselJobs()
    {
        $output = [];
        $status_priority = ['4', '3', '2'];

        foreach ($status_priority as $status) {
            $output = array_merge($output, $this->getCarouselJobsByStatus($status));
        }

        // Add normal jobs if needed to fill carousel
        if (count($output) < 3) {
            $normal_jobs = $this->getCarouselJobsByStatus('0', 3 - count($output));
            $output = array_merge($output, $normal_jobs);
        }

        return $output;
    }

    /**
     * Get archive title based on current query
     *
     * @return string
     */
    public function getArchiveTitle(): string
    {
        if (is_tax()) {
            $term = get_queried_object();
            return $term->name;
        }
        
        return 'Semua Lowongan';
    }

    /**
     * Get archive description
     *
     * @return string
     */
    public function getArchiveDescription(): string
    {
        if (is_tax()) {
            $term = get_queried_object();
            return !empty($term->description) ? $term->description : 'Lowongan pekerjaan tersedia';
        }
        
        return 'Temukan berbagai lowongan kerja yang tersedia';
    }
    /**
     * Get jobs with flexible filtering
     * 
     * @param int $paged Current page number
     * @param array $params Optional direct filter parameters (overrides $_GET)
     * @return array Query results and pagination data
     */
    public function getJobs(int $paged = 1, array $params = []): array
    {
        // Build query args
        $args = $this->buildJobQueryArgs($paged);
        
        // Process filter parameters
        $merged_params = $this->processFilterParameters($params);
        
        // Perform custom handling for company name search
        if (!empty($merged_params['keywords'])) {
            $keywords = sanitize_text_field($merged_params['keywords']);
            
            // Use a more efficient approach for company search
            add_filter('posts_join', function($join, $query) {
                global $wpdb;
                return $join . " LEFT JOIN {$wpdb->postmeta} as company_meta ON ({$wpdb->posts}.ID = company_meta.post_id AND company_meta.meta_key = 'nama_perusahaan') ";
            }, 10, 2);
            
            add_filter('posts_where', function($where, $query) use ($keywords) {
                global $wpdb;
                return $where . $wpdb->prepare(" OR (company_meta.meta_value LIKE %s) ", '%' . $wpdb->esc_like($keywords) . '%');
            }, 10, 2);
            
            $args['s'] = $keywords; // Still search in title/content too
        }
        
        // Get taxonomy query from TaxonomyController
        $tax_query = $this->taxonomyController->buildTaxonomyQueryFromParams($merged_params);
        
        // Add taxonomy query if we have conditions
        if (!empty($tax_query)) {
            $args['tax_query'] = [
                'relation' => 'AND',
                $tax_query
            ];
        }
        
        // Execute query through model (pure data access)
        return $this->model->executeJobQuery($args);
    }
}
