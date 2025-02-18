<?php
namespace AstraChild\Views\Homepage;

use AstraChild\Controllers\Ajax\StatusCarouselController;
use AstraChild\Controllers\page\HomePageController;
use AstraChild\Controllers\JobController;
use AstraChild\Views\Components\JobStatusBadge;
use AstraChild\Views\Components\JobDeadlineBadge;
use AstraChild\Helpers\JobHelpers;

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
                    <div class="overflow-hidden lg:mx-10 md:mx-5 px-0 md:px-2">
                        <div id="status-carousel" class="flex gap-3 md:gap-6 transition-transform duration-300" 
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
        // Get status attributes for consistent display
        $status = $job_entity->getAttribute('status');
        $status_attrs = JobHelpers::getJobStatusAttributes($status);
        
        // Process deadline info similar to JS
        $deadline_html = '';
        if ($job_entity->hasAttribute('deadline')) {
            $deadline = strtotime($job_entity->getAttribute('deadline'));
            $current_time = current_time('timestamp');
            $time_diff = $deadline - $current_time;
            $days_left = ceil($time_diff / (60 * 60 * 24));
            
            if ($time_diff > 0) {
                if ($days_left <= 3) {
                    $color_class = 'bg-yellow-100 text-yellow-800';
                    $deadline_text = $days_left . ' hari lagi';
                } else {
                    $color_class = 'bg-green-100 text-green-800';
                    $deadline_text = $days_left . ' hari lagi';
                }
            } else {
                $color_class = 'bg-red-100 text-red-800';
                $deadline_text = 'Berakhir ' . abs($days_left) . ' hari lalu';
            }
            
            $deadline_html = '
                <div class="absolute top-3 right-3 z-10">
                    <div class="flex items-center rounded-full px-2 py-1 text-xs ' . $color_class . ' shadow-sm">
                        <i class="fas fa-clock mr-1"></i>
                        <span class="font-medium">' . $deadline_text . '</span>
                    </div>
                </div>';
        }
        ?>
        <div class="status-carousel-item">
            <div class="relative bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 p-3 md:p-5 h-full flex flex-col justify-between">
                <!-- Badges Section -->
                <div class="badges-container min-h-[40px] relative mb-2">
                    <!-- Deadline Badge -->
                    <?php echo $deadline_html; ?>
                    
                    <!-- Status Badge -->
                    <div class="absolute top-3 left-3 z-10">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium <?php echo $status_attrs['class']; ?> shadow-sm">
                            <i class="<?php echo $status_attrs['icon']; ?> mr-1"></i>
                            <?php echo $status_attrs['label']; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Visual Divider -->
                <div class="border-b border-gray-100"></div>
                
                <!-- Job Title -->
                <h3 class="text-xl font-semibold text-gray-900 mb-4">
                    <a href="<?php echo esc_url($job_entity->getAttribute('permalink')); ?>" class="hover:text-blue-600 transition-colors">
                        <?php echo esc_html($job_entity->getAttribute('title')); ?>
                    </a>
                </h3>

                <div class="mb-0">
                    <!-- Company name stays full width -->
                    <?php if (!empty($job_entity->getAttribute('company'))): ?>
                    <p class="flex items-center text-gray-600 mb-2">
                        <i class="fas fa-building mr-2 text-blue-600"></i>
                        <span class="font-bold"><?php echo esc_html($job_entity->getAttribute('company')); ?></span>
                    </p>
                    <?php endif; ?>
                    
                    <!-- Flex container for location, education and experience -->
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        <!-- Location -->
                         <?php if (!empty($job_entity->getAttribute('location'))): ?>
                        <p class="flex items-center text-gray-500">
                            <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                            <?php echo esc_html($job_entity->getAttribute('location')); ?>
                        </p>
                        <?php endif; ?>
                        
                        <!-- Education when available -->
                        <?php if (!empty($job_entity->getAttribute('education'))): ?>
                        <p class="flex items-center text-gray-500">
                            <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>
                            <?php echo esc_html($job_entity->getAttribute('education')); ?>
                        </p>
                        <?php endif; ?>
                        
                        <!-- Experience when available -->
                        <?php if (!empty($job_entity->getAttribute('experience'))): ?>
                        <p class="flex items-center text-gray-500">
                            <i class="fas fa-history mr-2 text-blue-600"></i>
                            <?php echo esc_html($job_entity->getAttribute('experience')); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <a href="<?php echo esc_url($job_entity->getAttribute('permalink')); ?>" 
                       class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700">
                        Lihat Detail
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}