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
        <div class="status-carousel-item min-w-[280px] w-[280px] md:min-w-[320px] md:w-[320px]">
            <div class="bg-white rounded-lg shadow-sm border border-red-200 hover:shadow-md transition-shadow p-4">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <h3 class="font-semibold text-gray-900 line-clamp-2">
                        <a href="<?php echo esc_url($job_entity->getAttribute('permalink')); ?>" class="hover:text-blue-600 transition-colors">
                            <?php echo esc_html($job_entity->getAttribute('title')); ?>
                        </a>
                    </h3>
                    
                    <?php if ($job_entity->isUrgent()): ?>
                    <span class="bg-red-100 text-red-600 text-xs font-medium px-2 py-1 rounded-full whitespace-nowrap">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Urgent
                    </span>
                    <?php endif; ?>
                </div>
                
                <p class="text-sm text-gray-600 mb-3">
                    <i class="fas fa-building mr-1 text-blue-600"></i>
                    <?php echo esc_html($job_entity->getAttribute('company')); ?>
                </p>
                
                <?php if ($job_entity->hasAttribute('deadline')): 
                    $deadline = strtotime($job_entity->getAttribute('deadline'));
                    $days_left = ceil(($deadline - time()) / (60 * 60 * 24));
                ?>
                    <div class="text-xs font-medium <?php echo $days_left > 3 ? 'text-green-600' : 'text-red-600'; ?> mb-3">
                        <i class="fas fa-clock mr-1"></i>
                        <?php 
                        if ($days_left > 0) {
                            echo 'Berakhir dalam ' . $days_left . ' hari';
                        } else {
                            echo 'Berakhir ' . abs($days_left) . ' hari yang lalu';
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <a href="<?php echo esc_url($job_entity->getAttribute('permalink')); ?>" 
                   class="block text-center text-sm bg-blue-600 hover:bg-blue-700 text-white rounded py-1.5 px-4 transition-colors">
                    Lihat Detail
                </a>
            </div>
        </div>
        <?php
    }
}