<?php
namespace AstraChild\Controllers\Page;

use AstraChild\Models\JobModel;
use AstraChild\Controllers\JobController;

/**
 * Homepage Controller
 * 
 * Handles homepage template operations
 */
class HomePageController
{
    /**
     * @var JobModel
     */
    private $jobModel;

    /**
     * @var JobController
     */
    private $jobController;
    
    /**
     * Initialize the controller
     */
    public function __construct()
    {
        $this->jobModel = new JobModel();
        $this->jobController = new JobController();
        $this->registerHooks();
    }
    
    /**
     * Register WordPress hooks
     */
    private function registerHooks()
    {
        // Hook into template_include if needed for custom homepage logic
    }
    
    /**
     * Get featured jobs for homepage
     * 
     * @param int $page Current page number
     * @param array $filters Optional filter criteria
     * @param int $per_page Number of posts per page
     * @return array Featured jobs data
     */
    public function getFeaturedJobs($page = 1, $filters = [], $per_page = 10)
    {
        return $this->jobController->getFeaturedJobs($page, $filters, $per_page);
    }
    
    /**
     * Get carousel status jobs
     * 
     * @return array Status carousel jobs
     */
    public function getStatusCarouselJobs()
    {
        return $this->jobController->getStatusCarouselJobs();
    }
}