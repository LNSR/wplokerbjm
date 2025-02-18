<?php
namespace AstraChild\Controllers\Ajax;

use AstraChild\Views\Jobs\JobCard;
use AstraChild\Controllers\JobController;

/**
 * Job Search Scroll Controller
 * 
 * Handles infinite scroll functionality for job search results
 */
class JobSearchController extends BaseJobAjaxController
{
    /**
     * @var JobController
     */
    protected $jobController;
    
    /**
     * @var string AJAX action name
     */
    protected $action = 'job_search_scroll';
    
    /**
     * @var string Nonce name
     */
    protected $nonce = 'job_search_scroll_nonce';
    
    /**
     * Initialize controller
     */
    public function __construct()
    {
        // Initialize parent first
        parent::__construct();
        
        // Initialize JobController
        $this->jobController = new JobController();
    }

    /**
     * Handle job search infinite scroll request
     * 
     * @return void
     */
    public function handleRequest()
    {
        if (!$this->validateJobRequest('_ajax_nonce')) {
            return;
        }
        
        // Set paged parameter from AJAX request
        $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
        
        // Build filter parameters directly from POST data
        $params = [];
        foreach ($_POST as $key => $value) {
            // Skip AJAX-specific parameters
            if (!in_array($key, ['action', '_ajax_nonce', 'paged'])) {
                $params[$key] = sanitize_text_field($value);
            }
        }
        
        // Get search results using JobController instead of directly from model
        $search_results = $this->jobController->getFilteredJobs($paged, $params);
        
        // Capture output buffer to get HTML for new job cards
        ob_start();
        
        $job_card_view = new JobCard();
        if ($search_results['query']->have_posts()) {
            while ($search_results['query']->have_posts()) {
                $search_results['query']->the_post();
                $job_card_view->render(null, [
                    'show_statuses' => [
                        '0' => true,   // Show normal jobs
                        '2' => true,   // Show urgent jobs 
                        '3' => true,   // Show pinned jobs
                        '4' => true    // Show pinned & urgent jobs
                    ]
                ]);
            }
            wp_reset_postdata();
        }
        
        $html = ob_get_clean();
        
        // Calculate if there are more pages
        $has_more = ($paged < $search_results['query']->max_num_pages);
        
        // Use formatJobResponse to standardize response format
        $response = $this->formatJobResponse([
            'html' => $html,
            'hasMore' => $has_more,
            'foundPosts' => $search_results['found_posts'],
            'page' => $paged,
            'maxPages' => $search_results['query']->max_num_pages
        ]);
        
        wp_send_json_success($response);
    }
}