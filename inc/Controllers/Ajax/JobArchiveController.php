<?php
namespace AstraChild\Controllers\Ajax;

use AstraChild\Views\Jobs\JobCard;
use AstraChild\Controllers\JobController;
use AstraChild\Helpers\TaxonomyHelpers;

/**
 * Job Archive Controller
 * 
 * Handles AJAX load more for job archive pages
 */
class JobArchiveController extends BaseJobAjaxController
{
    /**
     * @var string AJAX action name
     */
    protected $action = 'load_archive_jobs';
    
    /**
     * @var string Nonce name
     */
    protected $nonce = 'archive_jobs_nonce';
    
    /**
     * @var JobController
     */
    protected $jobController;
    
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
     * Handle job archive load more request
     * 
     * @return void
     */
    public function handleRequest()
    {
        if (!$this->validateJobRequest('_ajax_nonce')) {
            return;
        }
        
        // Get paged parameter
        $paged = isset($_POST['page']) ? absint($_POST['page']) : 1;
        
        // Set up query based on the original archive query
        $args = [
            'post_type' => 'lowongan',
            'post_status' => 'publish',
            'posts_per_page' => get_option('posts_per_page', 10),
            'paged' => $paged
        ];
        
        // Handle taxonomy archives
        $queried_object = get_queried_object();
        $params = [];
        if (is_tax() && isset($queried_object->taxonomy) && isset($queried_object->term_id)) {
            // Fix: Use TaxonomyHelpers static method
            $param = TaxonomyHelpers::mapTaxonomyToParam($queried_object->taxonomy);
            if ($param) {
                $params[$param] = $queried_object->term_id;
            }
        }
        
        // Process custom query vars if provided
        if (isset($_POST['query_vars']) && !empty($_POST['query_vars'])) {
            $query_vars = json_decode(stripslashes($_POST['query_vars']), true);
            if (is_array($query_vars)) {
                $args = array_merge($args, $query_vars);
                if (isset($query_vars['tax_query'])) {
                    foreach ($query_vars['tax_query'] as $tax_query) {
                        if (isset($tax_query['taxonomy']) && isset($tax_query['terms'])) {
                            // Fix: Use TaxonomyHelpers static method
                            $param = TaxonomyHelpers::mapTaxonomyToParam($tax_query['taxonomy']);
                            if ($param) {
                                $params[$param] = $tax_query['terms'];
                            }
                        }
                    }
                }
            }
        }
        
        // Get results through the controller
        $search_results = $this->jobController->getJobs($paged, $params);
        
        // Buffer output
        ob_start();
        
        if ($search_results['query']->have_posts()) {
            $job_card_view = new JobCard();
            while ($search_results['query']->have_posts()) {
                $search_results['query']->the_post();
                $job_card_view->render();
            }
            wp_reset_postdata();
        }
        
        $html = ob_get_clean();
        
        // Response data
        $response = $this->formatJobResponse([
            'html' => $html,
            'page' => $paged,
            'maxPages' => $search_results['query']->max_num_pages,
            'hasMore' => ($paged < $search_results['query']->max_num_pages)
        ]);
        
        wp_send_json_success($response);
    }
}