<?php
namespace AstraChild\Controllers\Ajax;

use AstraChild\Models\JobModel;
use AstraChild\Controllers\JobController;

/**
 * Status Carousel Controller
 */
class StatusCarouselController extends BaseJobAjaxController
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
    protected $jobModel;

    /**
     * @var JobController
     */
    protected $jobController;
    
    /**
     * Initialize controller with required dependencies
     */
    public function __construct()
    {
        // Initialize parent
        parent::__construct();
        
        // Initialize model and controller
        $this->jobModel = new JobModel();
        $this->jobController = new JobController();
    }
    
    /**
     * Handle status carousel data request
     * 
     * @return void
     */
    public function handleRequest()
    {
        if (!$this->validateJobRequest('_ajax_nonce')) {
            return;
        }
        
        $carousel_jobs = $this->jobController->getStatusCarouselJobs();
        wp_send_json_success($carousel_jobs);
    }
}