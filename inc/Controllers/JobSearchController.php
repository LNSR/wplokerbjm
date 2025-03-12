<?php
namespace AstraChild\Controllers;

use AstraChild\Models\JobModel;

/**
 * Job Search Scroll Controller
 * 
 * Handles infinite scroll functionality for job search results
 */
class JobSearchController extends AjaxController
{
    /**
     * @var string AJAX action name
     */
    protected $action = 'job_search_scroll';
    
    /**
     * @var string Nonce name
     */
    protected $nonce = 'job_search_scroll_nonce';
    
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
     * Handle job search infinite scroll request
     * 
     * @return void
     */
    public function handleRequest()
    {
        if (!$this->verifyNonce('_ajax_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Set paged parameter from AJAX request
        $_GET = array_map('sanitize_text_field', $_POST);
        $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
        
        // Build search parameters
        $params = [];
        if (isset($_POST['keywords'])) $params['keywords'] = sanitize_text_field($_POST['keywords']);
        if (isset($_POST['location'])) $params['location'] = absint($_POST['location']);
        if (isset($_POST['job_type'])) $params['job_type'] = absint($_POST['job_type']);
        if (isset($_POST['education'])) $params['education'] = absint($_POST['education']);
        if (isset($_POST['experience'])) $params['experience'] = absint($_POST['experience']);
        if (isset($_POST['gender'])) $params['gender'] = absint($_POST['gender']);
        if (isset($_POST['salary'])) $params['salary'] = absint($_POST['salary']);
        
        $search_results = $this->jobModel->searchJobs($params, $paged);
        $query = $search_results['query'];
        
        ob_start();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                get_template_part('template-parts/homepage/content-job-card');
            }
        }
        
        wp_reset_postdata();
        $html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $html,
            'hasMore' => ($paged < $search_results['max_pages']),
            'maxPages' => $search_results['max_pages']
        ]);
    }
}