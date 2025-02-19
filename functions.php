<?php
/**
 * Astra Child Theme Functions
 * 
 * @package Astra-Child
 * @since 1.0.0
 */

// Exit if accessed directly for security
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define files to be included in the theme
 * These files contain various functionality components
 */
$include_files = [
    // Meta Box Configuration Files
    '/inc/meta-box/post-types.php',    // Custom post type definitions
    '/inc/meta-box/taxonomies.php',     // Custom taxonomy definitions
    '/inc/meta-box/custom-fields.php',  // Custom field configurations

    // Windpress files
    '/inc/windpress/scanner.php',       // WindPress Scanner

    // Helper Files
    '/inc/helpers/job-helpers.php',     // Job Helper
    '/inc/helpers/social-media.php',    // Social Media Helper
    
    
    // Asset Management
    // '/inc/enqueue.php'                  // Script and style enqueuing
];

// Include all defined files
foreach ($include_files as $file) {
    require_once get_stylesheet_directory() . $file;
}

/**
 * Updates the 'lowongan' post type to include specified taxonomies
 * This function runs after the initial post type registration
 * 
 * @since 1.0.0
 * @return void
 */
function update_lowongan_taxonomies() {
    $taxonomies = [
        'jenis-pekerjaan',    // Job Type
        'lokasi-pekerjaan',   // Job Location
        'kategori-lowongan',  // Job Category
        'gender',             // Gender Requirement
        'pendidikan',         // Education Requirement
        'pengalaman',         // Experience Requirement
        'gaji',              // Salary Range
        'usia'               // Age Requirement
    ];
    
    // Update the post type registration with new taxonomies
    global $wp_post_types;
    if (isset($wp_post_types['lowongan'])) {
        $wp_post_types['lowongan']->taxonomies = $taxonomies;
    }
}
add_action('init', 'update_lowongan_taxonomies', 11);

// Add to your existing functions.php
function handle_job_search() {
    check_ajax_referer('job_search_nonce', '_ajax_nonce');

    $paged = isset($_POST['page']) ? absint($_POST['page']) : 1;
    
    $args = [
        'post_type' => 'lowongan',
        'posts_per_page' => 6,
        'orderby' => 'date',
        'order' => 'DESC',
        'paged' => $paged // Add paged parameter
    ];

    // Only add search query if not empty
    $search = sanitize_text_field($_POST['s'] ?? '');
    if (!empty($search)) {
        $args['s'] = $search;
    }

    // Build taxonomy query
    $tax_query = [];
    
    // Add location filter
    $location = sanitize_text_field($_POST['lokasi-pekerjaan'] ?? '');
    if (!empty($location)) {
        $tax_query[] = [
            'taxonomy' => 'lokasi-pekerjaan',
            'field' => 'slug',
            'terms' => $location
        ];
    }

    // Add experience filter
    $experience = sanitize_text_field($_POST['pengalaman'] ?? '');
    if (!empty($experience)) {
        $tax_query[] = [
            'taxonomy' => 'pengalaman',
            'field' => 'slug',
            'terms' => $experience
        ];
    }

    // Add education filter
    $education = sanitize_text_field($_POST['pendidikan'] ?? '');
    if (!empty($education)) {
        $tax_query[] = [
            'taxonomy' => 'pendidikan',
            'field' => 'slug',
            'terms' => $education
        ];
    }

    // Only add tax query if we have any taxonomy conditions
    if (!empty($tax_query)) {
        $args['tax_query'] = [
            'relation' => 'AND',
            $tax_query
        ];
    }

    $query = new WP_Query($args);
    ob_start();

    if ($query->have_posts()) :
        ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-4">
            <?php
            while ($query->have_posts()) : $query->the_post();
                get_template_part('template-parts/content', 'job-card');
            endwhile;
            ?>
        </div>

        <?php if ($query->max_num_pages > 1) : ?>
            <div class="mt-8 flex justify-center gap-2">
                <?php 
                for ($i = 1; $i <= $query->max_num_pages; $i++) :
                    $is_current = $i === $paged;
                ?>
                    <button type="button"
                            data-page="<?php echo $i; ?>"
                            class="page-number px-4 py-2 rounded-lg <?php echo $is_current ? 
                                'bg-blue-600 text-white' : 
                                'bg-white text-blue-600 hover:bg-blue-50'; ?> 
                                border border-blue-200 transition-colors">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        <?php
    else:
        ?>
        <div class="text-center p-8 bg-gray-50 rounded-lg">
            <p class="text-gray-600">Tidak ada lowongan yang sesuai dengan kriteria pencarian.</p>
        </div>
        <?php
    endif;

    wp_reset_postdata();
    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html,
        'found_posts' => $query->found_posts,
        'max_pages' => $query->max_num_pages
    ]);
}

add_action('wp_ajax_search_jobs', 'handle_job_search');
add_action('wp_ajax_nopriv_search_jobs', 'handle_job_search');

// Enqueue the script
function enqueue_job_search_scripts() {
    if (is_page_template('page-homepage.php')) {
        wp_enqueue_script(
            'job-search', 
            get_stylesheet_directory_uri() . '/assets/js/job-search.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        // Correct way to localize script with an array
        wp_localize_script(
            'job-search', 
            'jobSearchData', 
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('job_search_nonce')
            )
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_job_search_scripts');

function load_featured_jobs() {
    check_ajax_referer('featured_jobs_nonce', '_ajax_nonce');

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    
    $args = [
        'post_type' => 'lowongan',
        'posts_per_page' => 6,
        'orderby' => 'date',
        'order' => 'DESC',
        'paged' => $page
    ];

    $query = new WP_Query($args);
    ob_start();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            get_template_part('template-parts/content', 'job-card');
        }
    } else {
        echo '<p class="text-gray-500 text-center">Tidak ada lowongan tersedia.</p>';
    }

    wp_reset_postdata();
    
    wp_send_json_success([
        'html' => ob_get_clean(),
        'found_posts' => $query->found_posts,
        'max_pages' => $query->max_num_pages
    ]);
}

add_action('wp_ajax_load_featured_jobs', 'load_featured_jobs');
add_action('wp_ajax_nopriv_load_featured_jobs', 'load_featured_jobs');

// Enqueue featured jobs script
function enqueue_featured_jobs_scripts() {
    if (is_page_template('page-homepage.php')) {
        wp_enqueue_script(
            'featured-jobs',
            get_stylesheet_directory_uri() . '/assets/js/featured-jobs.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script(
            'featured-jobs',
            'featuredJobsData',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('featured_jobs_nonce')
            ]
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_featured_jobs_scripts');