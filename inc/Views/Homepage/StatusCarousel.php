<?php
namespace AstraChild\Views\Homepage;

use AstraChild\Controllers\StatusCarouselController;
use AstraChild\Controllers\HomePageController;
use AstraChild\Controllers\JobController;
use AstraChild\Views\Components\JobStatusBadge;
use AstraChild\Views\Components\JobDeadlineBadge;

/**
 * Status Carousel View
 * 
 * Handles the rendering of the status carousel component
 */
class StatusCarousel
{
    /**
     * @var StatusCarouselController
     */
    protected $carouselController;
    
    /**
     * @var HomePageController
     */
    protected $homepagecontroller;
    
    /**
     * @var JobController
     */
    protected $jobController;

    /**
     * @var JobStatusBadge
     */
    protected $statusBadge;
    
    /**
     * @var JobDeadlineBadge
     */
    protected $deadlineBadge;
    
    /**
     * Initialize the carousel
     */
    public function __construct()
    {
        $this->carouselController = new StatusCarouselController();
        $this->homepagecontroller = new HomePageController();
        $this->jobController = new JobController();
        $this->statusBadge = new JobStatusBadge();
        $this->deadlineBadge = new JobDeadlineBadge();
    }
    
    /**
     * Render the carousel container
     * 
     * @param array $options Options for customizing the carousel
     * @return void
     */
    public function render(array $options = []): void
    {
        $default_options = [
            'title' => 'Direkomendasikan',
            'show_title' => true,
            'items_to_show' => 3,
            'auto_slide' => true,
            'show_navigation' => true
        ];
        
        $options = array_merge($default_options, $options);
        
        ?>
        <section class="job-status-section mb-12">
            <div class="max-w-7xl mx-auto">
                <?php if ($options['show_title']): ?>
                <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">
                    <?php echo esc_html($options['title']); ?>
                </h2>
                <?php endif; ?>
                
                <!-- Carousel Container -->
                <div class="relative">
                    <?php if ($options['show_navigation']): ?>
                    <!-- Carousel Navigation -->
                    <button id="prev-status" class="hidden md:block absolute left-0 top-1/2 -translate-y-1/2 -translate-x-5 z-10 bg-white rounded-full p-2 shadow-lg hover:bg-gray-50">
                        <i class="fas fa-chevron-left text-gray-600"></i>
                    </button>
                    
                    <button id="next-status" class="hidden md:block absolute right-0 top-1/2 -translate-y-1/2 translate-x-5 z-10 bg-white rounded-full p-2 shadow-lg hover:bg-gray-50">
                        <i class="fas fa-chevron-right text-gray-600"></i>
                    </button>
                    <?php endif; ?>

                    <!-- Carousel Content -->
                    <div class="overflow-hidden lg:mx-20">
                        <div id="status-carousel" class="flex gap-6 transition-transform duration-300 px-2" 
                             data-auto-slide="<?php echo $options['auto_slide'] ? 'true' : 'false'; ?>"
                             data-items="<?php echo esc_attr($options['items_to_show']); ?>">
                            <?php $this->renderInitialItems(); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div id="status-carousel-loading" class="text-center py-8 hidden">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
            </div>
        </section>
        <?php
    }
    
    /**
     * Render initial carousel items for improved SEO and performance
     * 
     * @return void
     */
    protected function renderInitialItems(): void
    {
        // Get initial jobs directly without AJAX for faster initial load
        $carousel_data = $this->homepagecontroller->getStatusCarouselJobs();
        
        if (!empty($carousel_data)) {
            foreach ($carousel_data as $job) {
                // Create a job entity from the array data
                $job_id = $job['id'];
                
                // Get full job entity
                $job_entity = $this->jobController->getJobEntity($job_id);
                
                if ($job_entity) {
                    $this->renderCarouselItem($job_entity);
                }
            }
        } else {
            echo '<div class="text-center p-4 w-full">No featured jobs found</div>';
        }
    }
    
    /**
     * Render a single carousel item
     * 
     * @param object $job_entity Job entity object
     * @return void
     */
    protected function renderCarouselItem($job_entity): void
    {
        ?>
        <div class="status-carousel-item">
            <!-- Load by AJAX -->
        </div>
        <?php
    }
}