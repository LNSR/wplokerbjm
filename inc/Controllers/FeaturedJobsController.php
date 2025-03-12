<?php
namespace AstraChild\Controllers;

use AstraChild\Models\JobModel;

/**
 * Featured Jobs Controller
 * 
 * Handles fetching featured jobs via AJAX
 */
class FeaturedJobsController extends AjaxController
{
    /**
     * @var string AJAX action name
     */
    protected $action = 'load_featured_jobs';
    
    /**
     * @var string Nonce name
     */
    protected $nonce = 'featured_jobs_nonce';
    
    /**
     * @var JobModel
     */
    private $jobModel;
    
    /**
     * Initialize the controller
     */
    public function __construct()
    {
        $this->jobModel = new JobModel();
        parent::__construct();
    }
    
    /**
     * Handle featured jobs request
     * 
     * @return void
     */
    public function handleRequest()
    {
        if (!$this->verifyNonce('_ajax_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $filters = isset($_POST['filters']) ? json_decode(stripslashes($_POST['filters']), true) : [];
        
        // Pass the filters to your job model
        $featured_jobs = $this->jobModel->getFeaturedJobs($page, $filters);
        
        // Start output buffer to capture template content
        ob_start();
        
        if ($featured_jobs['query']->have_posts()) {
            while ($featured_jobs['query']->have_posts()) {
                $featured_jobs['query']->the_post();
                
                // Set filter options for the template
                set_query_var('job_card_options', $filters);
                get_template_part('template-parts/homepage/content-job-card');
            }
            wp_reset_postdata();
        } else {
            echo '<p class="text-gray-500 text-center">Tidak ada lowongan tersedia.</p>';
        }
        
        $html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $html,
            'pagination' => [
                'current_page' => $page,
                'max_pages' => $featured_jobs['max_pages']
            ]
        ]);
    }
}