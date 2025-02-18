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

    $args = [
        'post_type' => 'lowongan',
        'posts_per_page' => 6,
        'orderby' => 'date',
        'order' => 'DESC',
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            while ($query->have_posts()) : $query->the_post();
                get_template_part('template-parts/content', 'job-card');
            endwhile;
            ?>
        </div>
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

    wp_send_json_success(['html' => $html]);
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