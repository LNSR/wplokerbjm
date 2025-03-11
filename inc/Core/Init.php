<?php

namespace AstraChild\Core;

/**
 * Bootstrap the theme
 */
class Init
{
    /**
     * Services to initialize
     */
    private $services = [];

    /**
     * Register default services
     */
    public function __construct()
    {
        $this->services = [
            // Core services
            Enqueue::class,
            Setup::class,

            // Windpress Integration
            \AstraChild\Integrations\WindPress\WindPressService::class,
            
            // Schema components
            \AstraChild\Models\Schema\PostTypes::class,
            \AstraChild\Models\Schema\Taxonomies::class,
            \AstraChild\Models\Schema\CustomFields::class,
            \AstraChild\Models\Schema\SchemaManager::class,
            
            // Controllers
            \AstraChild\Controllers\JobController::class,
            \AstraChild\Controllers\ShareController::class,
            \AstraChild\Controllers\FeaturedJobsController::class,
            \AstraChild\Controllers\StatusCarouselController::class,
            \AstraChild\Controllers\JobSearchController::class,
            \AstraChild\Controllers\HomePageController::class,
            // Add other controllers here
        ];
    }

    /**
     * Initialize all services
     */
    public function initialize()
    {
        foreach ($this->services as $service) {
            if (class_exists($service)) {
                new $service();
            }
        }
    }
    /**
     * Register a new service
     *
     * @param string $service_class Fully qualified class name
     * @return void
     */
    public function registerService(string $service_class): void
    {
        $this->services[] = $service_class;
    }
}
