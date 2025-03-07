<?php
namespace AstraChild\Controllers;

use AstraChild\Models\JobModel;

/**
 * Status Carousel Controller
 * 
 * Handles status carousel data for homepage
 */
class StatusCarouselController extends AjaxController
{
    /**
     * @var string AJAX action name
     */
    protected $action = 'load_status_carousel';
    
    /**
     * @var string Nonce name
     */
    protected $nonce = 'status_carousel_nonce';
    
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
     * Handle status carousel data request
     * 
     * @return void
     */
    public function handleRequest()
    {
        if (!$this->verifyNonce('_ajax_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        $carousel_jobs = $this->jobModel->getStatusCarouselJobs();
        
        wp_send_json_success($carousel_jobs);
    }
}