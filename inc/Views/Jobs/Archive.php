<?php
namespace AstraChild\Views\Jobs;

use AstraChild\Views\Components\Pagination;
use AstraChild\Views\Components\FilterDropdown;

class Archive 
{
    /**
     * @var Pagination
     */
    protected $pagination;
    
    /**
     * @var FilterDropdown
     */
    protected $filterDropdown;
    
    /**
     * Initialize components
     */
    public function __construct() 
    {
        $this->pagination = new Pagination();
        $this->filterDropdown = new FilterDropdown();
    }
    
    /**
     * Render the archive page content
     *
     * @param array $args Display arguments
     * @return void
     */
    public function render(array $args = []): void 
    {
        $default_args = [
            'title' => 'Semua Lowongan',
            'description' => 'Temukan berbagai lowongan kerja yang tersedia',
            'show_filters' => true
        ];
        
        $args = array_merge($default_args, $args);
        global $wp_query;
        
        $pagination_data = [
            'current_page' => max(1, get_query_var('paged')),
            'max_pages' => $wp_query->max_num_pages,
            'found_posts' => $wp_query->found_posts
        ];
        
        // Output archive header
        $this->renderHeader($args['title'], $args['description'], $pagination_data['found_posts']);
        
        // Output filters if enabled
        if ($args['show_filters']) {
            $this->renderFilters();
        }
        
        // Output job grid with current posts
        $this->renderJobGrid($pagination_data);
    }
    
    /**
     * Render archive header
     * 
     * @param string $title Archive title
     * @param string $description Archive description
     * @param int $count Number of posts found
     * @return void
     */
    protected function renderHeader(string $title, string $description, int $count): void 
    {
        ?>
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900"><?php echo esc_html($title); ?></h1>
            <p class="text-gray-600 mt-2"><?php echo esc_html($description); ?></p>
            <div class="text-blue-600 font-medium mt-4">
                <?php echo esc_html($count); ?> lowongan ditemukan
            </div>
        </header>
        <?php
    }
    
    /**
     * Render filter section
     * 
     * @return void
     */
    protected function renderFilters(): void 
    {
        $selected_location = get_query_var('lokasi-pekerjaan', '');
        $selected_type = get_query_var('jenis-pekerjaan', '');
        $selected_education = get_query_var('pendidikan', '');
        
        // Get filter options from taxonomies
        $locations = get_terms([
            'taxonomy' => 'lokasi-pekerjaan',
            'hide_empty' => true
        ]);
        
        $job_types = get_terms([
            'taxonomy' => 'jenis-pekerjaan',
            'hide_empty' => true
        ]);

        $education = get_terms([
            'taxonomy' => 'pendidikan',
            'hide_empty' => true
        ]);
        
        // Format options for dropdown
        $location_options = [];
        $job_type_options = [];
        $education_options = [];
        
        if (!is_wp_error($locations)) {
            foreach ($locations as $location) {
                $location_options[$location->slug] = $location->name;
            }
        }
        
        if (!is_wp_error($job_types)) {
            foreach ($job_types as $job_type) {
                $job_type_options[$job_type->slug] = $job_type->name;
            }
        }

        if (!is_wp_error($education)) {
            foreach ($education as $edu) {
                $education_options[$edu->slug] = $edu->name;
            }
        }

        ?>
        <form action="<?php echo esc_url(get_post_type_archive_link('archive-lowongan')); ?>" method="get" class="bg-white p-6 rounded-lg shadow mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php $this->filterDropdown->render('location', 'Lokasi', $location_options, $selected_location); ?>
                <?php $this->filterDropdown->render('job_type', 'Jenis Pekerjaan', $job_type_options, $selected_type); ?>
                <?php $this->filterDropdown->render('education', 'Pendidikan', $education_options, $selected_education); ?>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded transition-colors items-center">
                        Filter Lowongan
                    </button>
                </div>
            </div>
        </form>
        <?php
    }
    
    /**
     * Render job grid
     * 
     * @param array $pagination_data Pagination information
     * @return void
     */
    protected function renderJobGrid(array $pagination_data): void 
    {
        ?>
        <div id="job-archive-grid" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php
            if (have_posts()) :
                // Initialize card view
                $job_card_view = new JobCard();
                
                while (have_posts()) : the_post();
                    $job_card_view->render();
                endwhile;
            else :
                ?>
                <div class="col-span-full text-center p-8 bg-gray-50 rounded-lg">
                    <p class="text-gray-600">Tidak ada lowongan yang tersedia saat ini.</p>
                </div>
                <?php
            endif;
            ?>
        </div>
        
        <?php if ($pagination_data['max_pages'] > 1) : ?>
            <div class="mt-8">
                <?php $this->pagination->render($pagination_data); ?>
            </div>
        <?php endif; ?>
        <?php
    }
}