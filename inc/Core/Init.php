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
            \AstraChild\Core\Enqueue::class,
            \AstraChild\Core\Setup::class,

            // Windpress Integration
            \AstraChild\Integrations\WindPress\WindPressService::class,
            
            // Schema components
            \AstraChild\Models\Schema\PostTypes::class,
            \AstraChild\Models\Schema\Taxonomies::class,
            \AstraChild\Models\Schema\CustomFields::class,
            \AstraChild\Models\Schema\SchemaManager::class,
            
            // Controllers
            \AstraChild\Controllers\TaxononomyController::class,
            \AstraChild\Controllers\JobController::class,
                \AstraChild\Controllers\Page\HomePageController::class,
                \AstraChild\Controllers\Page\ArchiveController::class,

                // AJAX
                \AstraChild\Controllers\Ajax\ShareController::class,
                \AstraChild\Controllers\Ajax\FeaturedJobsController::class,
                \AstraChild\Controllers\Ajax\StatusCarouselController::class,
                \AstraChild\Controllers\Ajax\JobSearchController::class,
                \AstraChild\Controllers\Ajax\JobArchiveController::class,
            
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
