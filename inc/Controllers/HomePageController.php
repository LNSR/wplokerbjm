<?php
namespace AstraChild\Controllers;

use AstraChild\Models\JobModel;

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
     * Initialize the controller
     */
    public function __construct()
    {
        $this->jobModel = new JobModel();
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
     * @return array Featured jobs data
     */
    public function getFeaturedJobs($page = 1, $filters = [])
    {
        return $this->jobModel->getFeaturedJobs($page, $filters);
    }
    
    
    /**
     * Get carousel status jobs
     * 
     * @return array Status carousel jobs
     */
    public function getStatusCarouselJobs()
    {
        return $this->jobModel->getStatusCarouselJobs();
    }
}