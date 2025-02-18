<?php
namespace AstraChild\Views\Jobs;

use AstraChild\Views\Components\FilterDropdown;
use AstraChild\Views\Components\LoadMore;
use AstraChild\Views\Jobs\JobCard;
use AstraChild\Views\Components\EmptyState;
use AstraChild\Views\Components\FilterSummary;
use AstraChild\Views\Components\HierarchicalFilterDropdown;
use AstraChild\Controllers\Page\ArchiveController;

/**
 * Archive View
 * 
 * Handles rendering of job listing archives
 */
class Archive 
{
    /**
     * @var FilterDropdown
     */
    protected $filterDropdown;

    /**
     * @var LoadMore
     */
    protected $loadMore;
    
    /**
     * @var EmptyState
     */
    protected $emptyState;

    /**
     * @var FilterSummary
     */
    protected $filterSummary;

    /**
     * @var HierarchicalFilterDropdown
     */
    protected $hierarchicalFilterDropdown;

    /**
     * @var JobModel
     */
    protected $jobModel;

    /**
     * @var ArchiveController
     */
    protected $archiveController;

    /**
     * Initialize Archive view
     */
    public function __construct() 
    {
        $this->filterDropdown = new FilterDropdown();
        $this->loadMore = new LoadMore();
        $this->emptyState = new EmptyState();
        $this->filterSummary = new FilterSummary();
        $this->hierarchicalFilterDropdown = new HierarchicalFilterDropdown();
        $this->archiveController = new ArchiveController();
    }
    
    /**
     * Render the job listing archive
     * 
     * @param array $args Additional arguments
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
        
        // Existing code that builds pagination_data, but rename the variable
        $page_data = [
            'current_page' => max(1, get_query_var('paged')),
            'max_pages' => $wp_query->max_num_pages,
            'found_posts' => $wp_query->found_posts
        ];
        
        // Pass the renamed variable to these methods
        $this->renderHeader($args['title'], $args['description'], $page_data['found_posts']);
        
        // Output filters if enabled
        if ($args['show_filters']) {
            $this->renderFilters();
        }
        
        // Output job grid with current posts
        $this->renderJobGrid($page_data);
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
        // Get filter options through controller
        $filter_options = $this->archiveController->getFilterOptions();
        
        // Determine which filters are currently selected
        $selected_filters = [];
        foreach ($filter_options as $param => $options) {
            $selected_filters[$param] = isset($_GET[$param]) ? sanitize_text_field($_GET[$param]) : '';
        }
        
        ?>
        <form action="<?php echo esc_url(get_post_type_archive_link('lowongan')); ?>" method="get" class="bg-white p-6 rounded-lg shadow mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($filter_options as $param => $config): ?>
                    <?php if ($config['type'] === 'hierarchical'): ?>
                        <?php $this->hierarchicalFilterDropdown->render(
                            $param, 
                            $config['label'], 
                            $config['terms'], 
                            $selected_filters[$param]
                        ); ?>
                    <?php else: ?>
                        <?php $this->filterDropdown->render(
                            $param, 
                            $config['label'], 
                            $config['terms'], 
                            $selected_filters[$param]
                        ); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded transition-colors">
                        Filter Lowongan
                    </button>
                </div>
            </div>
        </form>
        <?php
        // Get active filters from the controller
        $active_filters = $this->archiveController->getActiveFilters();
        $this->filterSummary->render($active_filters);
    }
    
    /**
     * Render job grid with items
     * 
     * @param array $page_data Page and result information
     * @return void 
     */
    protected function renderJobGrid(array $page_data): void 
    {
        ?>
        <div id="job-archive-grid" class="grid grid-cols-1 md:grid-cols-2 gap-6"
             <?php if (is_tax()) echo 'data-query-vars="' . esc_attr(json_encode(['tax_query' => [['taxonomy' => get_queried_object()->taxonomy, 'field' => 'term_id', 'terms' => get_queried_object()->term_id]]])) . '"'; ?>>
            <?php
            if (have_posts()) :
                // Initialize card view
                $job_card_view = new JobCard();
                
                while (have_posts()) : the_post();
                    $job_card_view->render();
                endwhile;
            else :
                $this->emptyState->render('Tidak ada lowongan yang tersedia saat ini.');
            endif;
            ?>
        </div>
        
        <?php if ($page_data['max_pages'] > 1) : ?>
            <?php $this->loadMore->render($page_data, 'job-archive-grid', 'loadMoreJobs'); ?>
            
            <!-- Loading indicator -->
            <div id="job-archive-loading" class="text-center py-8 hidden">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        <?php endif; ?>
        <?php
    }
}