<?php
namespace AstraChild\Views\Homepage;

use AstraChild\Controllers\HomePageController;
use AstraChild\Views\Components\Pagination;

/**
 * Featured Jobs Grid View
 * 
 * Displays the featured jobs grid on homepage
 */
class FeaturedJobs
{
    /**
     * @var HomePageController
     */
    protected $homeController;
    
    /**
     * @var Pagination
     */
    protected $pagination;
    
    /**
     * Initialize the featured jobs grid
     */
    public function __construct()
    {
        $this->homeController = new HomePageController();
        $this->pagination = new Pagination();
    }
    
    /**
     * Render the featured jobs grid
     * 
     * @param array $options Options for customizing the grid
     * @return void
     */
    public function render(array $options = []): void
    {
        $default_options = [
            'title' => 'Lowongan Terbaru',
            'show_title' => true,
            'columns' => [
                'mobile' => 1,
                'tablet' => 1,
                'desktop' => 1
            ],
            'max_width' => 'max-w-7xl',
            'margin_x' => 'mx-auto lg:mx-50',
            'show_pagination' => true,
            'grid_gap' => 'gap-6',
            'filter_jobs' => [
                'show_statuses' => [
                    '0' => true,   // Show normal jobs
                    '2' => true,   // Show urgent jobs
                    '3' => false,  // Hide pinned jobs
                    '4' => false   // Hide pinned & urgent jobs
                ]
            ]
        ];
        
        $options = array_merge($default_options, $options);
        
        // Get current page from query vars
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        
        // Get featured jobs from controller
        $featured_jobs = $this->homeController->getFeaturedJobs($paged);
        $query = $featured_jobs['query'];
        
        // Generate grid columns classes
        $grid_columns = 'grid-cols-' . $options['columns']['mobile'] . 
                        ' md:grid-cols-' . $options['columns']['tablet'] . 
                        ' lg:grid-cols-' . $options['columns']['desktop'];
        
        ?>
        <section class="featured-jobs-section mb-12">
            <div class="<?php echo esc_attr($options['max_width'] . ' ' . $options['margin_x']); ?>">
                <?php if ($options['show_title']): ?>
                <h2 class="text-3xl font-bold text-gray-900 mb-8"><?php echo esc_html($options['title']); ?></h2>
                <?php endif; ?>
                
                <div id="featured-jobs-grid" class="grid <?php echo esc_attr($grid_columns . ' ' . $options['grid_gap']); ?>">
                    <?php
                    if ($query->have_posts()) :
                        while ($query->have_posts()) : $query->the_post();
                            // Pass filter options to job card
                            set_query_var('job_card_options', $options['filter_jobs']);
                            get_template_part('template-parts/homepage/content-job-card');
                        endwhile;
                        wp_reset_postdata();
                    else :
                        echo '<p class="text-gray-500 text-center">Tidak ada lowongan tersedia.</p>';
                    endif;
                    ?>
                </div>
                
                <?php if ($options['show_pagination']): ?>
                    <?php $this->pagination->render(
                        $featured_jobs,
                        'featured-jobs',
                        'loadFeaturedJobs'
                    ); ?>
                    
                    <!-- Loading indicator -->
                    <div id="featured-jobs-loading" class="text-center py-8 hidden">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}