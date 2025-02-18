<?php
namespace AstraChild\Controllers\Ajax;

use AstraChild\Models\JobModel;
use AstraChild\Controllers\AjaxController;
use AstraChild\Helpers\TaxonomyHelpers;

/**
 * Base controller for job-related AJAX requests
 */
abstract class BaseJobAjaxController extends AjaxController
{
    /**
     * @var string AJAX action name
     */
    protected $action;
    
    /**
     * @var string Nonce name
     */
    protected $nonce;
    
    /**
     * @var JobModel
     */
    protected $jobModel;
    
    /**
     * Initialize base controller
     */
    public function __construct() 
    {
        $this->jobModel = new JobModel();
        parent::__construct();
        
        // Register AJAX actions
        add_action('wp_ajax_' . $this->action, [$this, 'handleRequest']);
        add_action('wp_ajax_nopriv_' . $this->action, [$this, 'handleRequest']);
    }
    
    /**
     * Handle the AJAX request
     */
    abstract public function handleRequest();
    
    /**
     * Validate job request with nonce
     */
    protected function validateJobRequest($nonce_key = '_wpnonce', $post_id = null) 
    {
        // Check nonce
        if (!isset($_POST[$nonce_key]) || !wp_verify_nonce($_POST[$nonce_key], $this->nonce)) {
            wp_send_json_error(['message' => 'Invalid security token'], 403);
            return false;
        }
        
        if ($post_id !== null) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'lowongan') {
                wp_send_json_error(['message' => 'Invalid job posting']);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Format standard job response
     */
    protected function formatJobResponse(array $data) 
    {
        return array_merge([
            'html' => '',
            'hasMore' => false,
            'foundPosts' => 0,
            'page' => 1,
            'maxPages' => 1
        ], $data);
    }
    
    /**
     * Map taxonomy to URL parameter
     * 
     * @deprecated Use TaxonomyHelpers::mapTaxonomyToParam() instead
     */
    protected function mapTaxonomyToParam(string $taxonomy): ?string 
    {
        return TaxonomyHelpers::mapTaxonomyToParam($taxonomy);
    }
}