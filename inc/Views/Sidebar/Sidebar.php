<?php
namespace AstraChild\Views\Sidebar;

use AstraChild\Controllers\JobController;

/**
 * Sidebar View
 * 
 * Handles rendering of sidebar components
 */
class Sidebar
{
    /**
     * @var JobController
     */
    protected $jobController;
    
    /**
     * Initialize the sidebar view
     */
    public function __construct()
    {
        $this->jobController = new JobController();
    }
    
    /**
     * Render the sidebar
     * 
     * @param array $options Options for customizing the sidebar
     * @return void
     */
    public function render(array $options = []): void
    {
        $default_options = [
            'show_recent_jobs' => true,
            'show_categories' => true,
            'recent_job_count' => 5
        ];
        
        $options = array_merge($default_options, $options);
        
        // Get data for sidebar components
        $recent_jobs_data = null;
        if ($options['show_recent_jobs']) {
            $recent_jobs_data = $this->jobController->getRecentJobs($options['recent_job_count']);
        }
        
        // Make data available to template
        set_query_var('recent_jobs_data', $recent_jobs_data);
        set_query_var('job_controller', $this->jobController);
        set_query_var('options', $options);
        set_query_var('view', $this);
        
        // Include template
        include get_stylesheet_directory() . '/template-parts/sidebar/content.php';
    }
    
    /**
     * Render recent jobs component
     * 
     * @param array $recent_jobs_data Recent jobs data
     * @param JobController $job_controller The job controller instance
     * @return void
     */
    public function renderRecentJobs($recent_jobs_data, $job_controller): void
    {
        if (empty($recent_jobs_data) || !$recent_jobs_data['query']->have_posts()) {
            echo '<p class="text-gray-500 text-center">Tidak ada lowongan terbaru.</p>';
            return;
        }
        
        echo '<div class="space-y-4">';
        
        while ($recent_jobs_data['query']->have_posts()) {
            $recent_jobs_data['query']->the_post();
            $job_id = get_the_ID();
            $job_entity = $job_controller->getJobEntity($job_id);
            
            $this->renderJobItem($job_entity);
        }
        
        wp_reset_postdata();
        
        echo '</div>';
    }
    
    /**
     * Render a single job item
     * 
     * @param object $job_entity The job entity
     * @return void
     */
    protected function renderJobItem($job_entity): void
    {
        ?>
        <article class="group">
            <a href="<?php echo esc_url($job_entity->getAttribute('permalink')); ?>" class="block hover:bg-gray-50 rounded-lg p-3 transition-colors duration-200">
                <h4 class="text-gray-900 font-medium group-hover:text-blue-600 line-clamp-2">
                    <?php echo esc_html($job_entity->getAttribute('title')); ?>
                </h4>
                <?php if ($job_entity->hasAttribute('company')) : ?>
                    <p class="text-sm text-gray-600 mt-1">
                        <?php echo esc_html($job_entity->getAttribute('company')); ?>
                    </p>
                <?php endif; ?>
            </a>
        </article>
        <?php
    }
    
    /**
     * Render categories component
     * 
     * @return void
     */
    public function renderCategories(): void
    {
        $categories = get_terms([
            'taxonomy' => 'kategori-lowongan',
            'hide_empty' => true
        ]);
        
        if (empty($categories) || is_wp_error($categories)) {
            echo '<p class="text-gray-500 text-center">Tidak ada kategori.</p>';
            return;
        }
        
        echo '<div class="space-y-2">';
        
        foreach ($categories as $category) {
            $this->renderCategoryItem($category);
        }
        
        echo '</div>';
    }
    
    /**
     * Render a single category item
     * 
     * @param object $category The category term object
     * @return void
     */
    protected function renderCategoryItem($category): void
    {
        ?>
        <a href="<?php echo esc_url(get_term_link($category)); ?>" 
           class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
            <span class="text-gray-700 hover:text-blue-600">
                <?php echo esc_html($category->name); ?>
            </span>
            <span class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                <?php echo esc_html($category->count); ?>
            </span>
        </a>
        <?php
    }
}