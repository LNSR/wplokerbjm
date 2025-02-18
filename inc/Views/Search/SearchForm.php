<?php
namespace AstraChild\Views\Search;

use AstraChild\Controllers\JobController;
use AstraChild\Controllers\TaxonomyController;

/**
 * Search Form View
 * 
 * Handles rendering of job search forms
 */
class SearchForm
{
    /**
     * @var JobController
     */
    protected $jobController;
    
    /**
     * @var TaxonomyController
     */
    protected $taxonomyController;
    
    /**
     * @var array Filter data for dropdowns
     */
    protected $filter_data;
    
    /**
     * @var bool Whether the current page is a search results page
     */
    protected $is_search_results_page;
    
    /**
     * Initialize the view
     */
    public function __construct(?JobController $jobController = null, ?TaxonomyController $taxonomyController = null)
    {
        // Allow dependency injection or create controllers if not provided
        $this->jobController = $jobController ?? new JobController();
        $this->taxonomyController = $taxonomyController ?? new TaxonomyController();
        
        // Get context from current request
        $context = $this->getFilterContext();
        
        // Get filter data through controller
        $this->filter_data = $this->taxonomyController->getSmartFilterData($context);
        $this->is_search_results_page = strpos($_SERVER['REQUEST_URI'] ?? '', 'search-jobs') !== false;
    }
    
    /**
     * Get filter context from request
     * 
     * @return array Filter context
     */
    protected function getFilterContext(): array
    {
        $context = [];
        $param_keys = ['loc', 'jenis', 'gender', 'pendidikan', 'pengalaman', 'gaji', 'usia', 'kategori'];
        
        foreach ($param_keys as $key) {
            if (isset($_GET[$key]) && !empty($_GET[$key])) {
                $context[$key] = sanitize_text_field($_GET[$key]);
            }
        }
        
        return $context;
    }
    
    /**
     * Render the search form
     * 
     * @param array $options Options for customizing the form
     * @return void
     */
    public function render(array $options = []): void
    {
        $default_options = [
            'title' => 'Temukan Lowongan Kerja Terbaik',
            'subtitle' => 'Temukan ribuan lowongan kerja di Banjarmasin dan sekitarnya',
            'show_title' => true,
            'desktop_margin' => 'lg:mx-60',  // Default for homepage
            'mobile_margin' => 'md:mx-auto'  // Default for mobile
        ];
        
        $options = array_merge($default_options, $options);
        ?>
        <section class="bg-gradient-to-r rounded-2xl p-8 mb-12">
            <?php if ($options['show_title']): ?>
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl font-bold text-white mb-4"><?php echo esc_html($options['title']); ?></h1>
                <p class="text-blue-300 font-semibold mx-auto mb-8"><?php echo esc_html($options['subtitle']); ?></p>
            </div>
            <?php endif; ?>

            <!-- Search Form with configurable margins -->
            <div class="bg-white p-6 rounded-xl shadow-lg <?php echo esc_attr($options['mobile_margin'] . ' ' . $options['desktop_margin']); ?>">
                <form action="<?php echo esc_url(home_url('/search-jobs/')); ?>" method="GET" class="space-y-6">
                    <!-- Search Input -->
                    <?php $this->renderKeywordInput(); ?>

                    <!-- Filters -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Location Filter -->
                        <?php $this->renderLocationFilter(); ?>
                        
                        <!-- Experience Filter -->
                        <?php $this->renderExperienceFilter(); ?>
                        
                        <!-- Education Filter -->
                        <?php $this->renderEducationFilter(); ?>
                    </div>

                    <!-- Buttons Container -->
                    <?php $this->renderActionButtons(); ?>
                </form>
            </div>
        </section>
        <?php
    }
    
    /**
     * Render keyword search input
     * 
     * @return void
     */
    protected function renderKeywordInput(): void
    {
        ?>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
            </div>
            <input type="text" 
                   name="keywords" 
                   placeholder="Cari berdasarkan posisi, perusahaan, atau kata kunci"
                   value="<?php echo isset($_GET['keywords']) ? esc_attr($_GET['keywords']) : ''; ?>"
                   class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 indent-8">
        </div>
        <?php
    }
    
    /**
     * Render location filter dropdown with hierarchical support
     * 
     * @return void
     */
    protected function renderLocationFilter(): void
    {
        // Get hierarchical terms
        $hierarchical_terms = $this->taxonomyController->getHierarchicalTerms('lokasi-pekerjaan');
        
        if (empty($hierarchical_terms)) {
            return;
        }
        
        ?>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-map-marker-alt text-gray-400"></i>
            </div>
            <select name="loc" class="w-full pl-12 pr-4 py-4 rounded-lg border text-center border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 appearance-none bg-white">
                <option value="">Lokasi</option>
                <?php $this->renderHierarchicalOptions($hierarchical_terms, isset($_GET['loc']) ? $_GET['loc'] : ''); ?>
            </select>
            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                <i class="fas fa-chevron-down text-gray-400"></i>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render hierarchical options for select dropdown
     * 
     * @param array $terms Hierarchical terms
     * @param string $selected_value Currently selected value
     * @param int $depth Current depth for indentation
     * @return void
     */
    protected function renderHierarchicalOptions(array $terms, string $selected_value = '', int $depth = 0): void
    {
        foreach ($terms as $term_data) {
            if (!isset($term_data['term'])) continue;
            
            $term = $term_data['term'];
            $indentation = str_repeat('&nbsp;&nbsp;', $depth);
            
            printf(
                '<option value="%s"%s>%s%s</option>',
                esc_attr($term->slug),
                selected($selected_value, $term->slug, false),
                $indentation,
                esc_html($term->name)
            );
            
            // Recursive rendering of children
            if (!empty($term_data['children'])) {
                $this->renderHierarchicalOptions($term_data['children'], $selected_value, $depth + 1);
            }
        }
    }
    
    /**
     * Render experience filter dropdown with progressive disclosure
     */
    protected function renderExperienceFilter(): void
    {
        if (empty($this->filter_data['experiences'])) {
            return;
        }
        
        $initial_limit = 5; // Show first 5 by default
        $experiences = $this->filter_data['experiences'];
        $has_more = count($experiences) > $initial_limit;
        ?>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-history text-gray-400"></i>
            </div>
            <select name="pengalaman" id="pengalaman-filter" class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 appearance-none bg-white text-center">
                <option value="">Pengalaman</option>
                
                <?php 
                // Show initial options
                foreach (array_slice($experiences, 0, $initial_limit) as $exp) : ?>
                <option value="<?php echo esc_attr($exp->slug); ?>"<?php echo isset($_GET['pengalaman']) && $_GET['pengalaman'] === $exp->slug ? ' selected' : ''; ?>>
                    <?php echo esc_html($exp->name); ?>
                </option>
                <?php endforeach; ?>
                
                <?php if ($has_more): ?>
                <option disabled>────────────</option>
                <?php endif; ?>
                
                <div class="more-options hidden">
                    <?php 
                    // Additional options initially hidden via JS
                    foreach (array_slice($experiences, $initial_limit) as $exp) : ?>
                    <option value="<?php echo esc_attr($exp->slug); ?>" class="more-option"<?php echo isset($_GET['pengalaman']) && $_GET['pengalaman'] === $exp->slug ? ' selected' : ''; ?>>
                        <?php echo esc_html($exp->name); ?>
                    </option>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($has_more): ?>
                <option value="show-more" class="show-more-option text-blue-600">Tampilkan Lainnya...</option>
                <?php endif; ?>
            </select>
            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                <i class="fas fa-chevron-down text-gray-400"></i>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render education filter dropdown with hierarchical support
     * 
     * @return void
     */
    protected function renderEducationFilter(): void
    {
        // Get hierarchical terms
        $hierarchical_terms = $this->taxonomyController->getHierarchicalTerms('pendidikan');
        
        if (empty($hierarchical_terms)) {
            return;
        }
        
        ?>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-graduation-cap text-gray-400"></i>
            </div>
            <select name="pendidikan" class="w-full pl-12 pr-4 py-4 rounded-lg border text-center border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 appearance-none bg-white">
                <option value="">Lulusan</option>
                <?php $this->renderHierarchicalOptions($hierarchical_terms, isset($_GET['pendidikan']) ? $_GET['pendidikan'] : ''); ?>
            </select>
            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                <i class="fas fa-chevron-down text-gray-400"></i>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render action buttons
     * 
     * @return void
     */
    protected function renderActionButtons(): void
    {
        ?>
        <div class="flex flex-row justify-center items-center gap-4">
            <!-- Search Button -->
            <button type="submit" class="w-auto px-4 md:px-8 py-3 md:py-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors duration-200 flex items-center justify-center gap-2 text-sm md:text-base">
                <i class="fas fa-search"></i>
                <span>Cari</span>
            </button>

            <?php if ($this->is_search_results_page): ?>
            <!-- Reset Button -->
            <button type="button" onclick="window.location.href='<?php echo esc_url(home_url('/')); ?>'" class="w-auto px-4 md:px-8 py-3 md:py-4 text-blue-600 hover:text-blue-700 font-semibold rounded-lg border-2 border-blue-600 hover:border-blue-700 transition-colors duration-200 flex items-center justify-center gap-2 text-sm md:text-base">
                <i class="fas fa-undo"></i>
                <span>Reset</span>
            </button>
            <?php endif; ?>
        </div>
        <?php
    }
}