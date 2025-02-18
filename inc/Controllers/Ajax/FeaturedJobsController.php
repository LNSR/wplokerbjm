<?php
namespace AstraChild\Controllers\Ajax;

use AstraChild\Controllers\JobController;
/**
 * Featured Jobs Controller
 * 
 * Handles fetching featured jobs via AJAX
 */
class FeaturedJobsController extends BaseJobAjaxController
{
    /**
     * @var JobController
     */
    protected $jobController;

    /**
     * @var string AJAX action name
     */
    protected $action = 'load_featured_jobs';
    
    /**
     * @var string Nonce name
     */
    protected $nonce = 'featured_jobs_nonce';

    /**
     * Initialize controller
     */
    public function __construct()
    {
        // Initialize parent
        parent::__construct();
        
        // Initialize JobController
        $this->jobController = new JobController();
    }

    
    /**
     * Handle AJAX request for loading more featured jobs
     */
    public function handleRequest()
    {
        if (!$this->validateJobRequest('_ajax_nonce')) {
            return;
        }
        
        // Get page parameter
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        
        // Get filters if provided
        $filters = [];
        if (isset($_POST['filters'])) {
            $filters = json_decode(stripslashes($_POST['filters']), true);
        }
        
        // Get featured jobs
        $featured_jobs = $this->jobController->getFeaturedJobs($page, $filters);
        
        // Prepare output
        ob_start();
        if ($featured_jobs['query']->have_posts()) {
            while ($featured_jobs['query']->have_posts()) {
                $featured_jobs['query']->the_post();
                // Set filter options
                set_query_var('job_card_options', $filters);
                get_template_part('template-parts/homepage/content-job-card');
            }
            wp_reset_postdata();
        }
        $html = ob_get_clean();
        
        // Send response
        wp_send_json_success([
            'html' => $html,
            'currentPage' => $page,
            'maxPages' => $featured_jobs['max_pages'],
            'hasMore' => ($page < $featured_jobs['max_pages'])
        ]);
    }
}