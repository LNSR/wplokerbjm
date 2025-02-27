<?php

function get_job_meta_data() {
    return [
        'company' => rwmb_meta('nama_perusahaan'),
        'company_desc' => rwmb_meta('tentang_perusahaan'),
        'job_desc' => rwmb_meta('deskripsi_pekerjaan'),
        'education' => rwmb_meta('pendidikan'),
        'job_type' => rwmb_meta('jenis_pekerjaan'),
        'gender' => rwmb_meta('gender'),
        'min_age' => rwmb_meta('umur_min'),
        'max_age' => rwmb_meta('umur_max'),
        'experience' => rwmb_meta('pengalaman'),
        'requirements' => rwmb_meta('persyaratan'),
        'min_salary' => rwmb_meta('gaji_minimal'),
        'max_salary' => rwmb_meta('gaji_maksimal'),
        'location' => rwmb_meta('lokasi'),
        'deadline' => rwmb_meta('deadline'),
        'email' => rwmb_meta('email_kontak'),
        'phone' => rwmb_meta('nomor_kontak'),
        'website' => rwmb_meta('situs_kontak'),
        'socials' => rwmb_meta('social_media'),
        'status' => rwmb_meta('status_pekerjaan')
    ];
}

/**
 * Get job taxonomies
 * 
 * @param string $taxonomy_name Name of the taxonomy to fetch
 * @param array $args Additional arguments for get_terms()
 * @return array Array of taxonomy terms or empty array if none found
 */
function get_job_taxonomy_terms($taxonomy_name, $args = []) {
    $default_args = [
        'taxonomy' => $taxonomy_name,
        'hide_empty' => false
    ];

    $args = wp_parse_args($args, $default_args);
    $terms = get_terms($args);

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    return $terms;
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

/**
 * Get all job related taxonomies data
 * 
 * @return array Associative array of all taxonomy terms
 */
function get_job_filters_data()
{
    return [
        'locations' => get_job_taxonomy_terms('lokasi-pekerjaan'),
        'experiences' => get_job_taxonomy_terms('pengalaman'),
        'education' => get_job_taxonomy_terms('pendidikan'),
        'job_types' => get_job_taxonomy_terms('jenis-pekerjaan'),
        'genders' => get_job_taxonomy_terms('gender'),
        'salaries' => get_job_taxonomy_terms('gaji'),
        'ages' => get_job_taxonomy_terms('usia')
    ];
}


/**
 * Get featured jobs data
 * 
 * @param int $page Current page number
 * @return array Query results and pagination data
 */
function get_featured_jobs_data($page = 1) {
    $args = [
        'post_type' => 'lowongan',
        'posts_per_page' => 6,
        'orderby' => 'date',
        'order' => 'DESC',
        'paged' => $page
    ];

    $query = new WP_Query($args);

    return [
        'query' => $query,
        'max_pages' => $query->max_num_pages,
        'current_page' => $page,
        'found_posts' => $query->found_posts
    ];
}

// Add this to the bottom of your file

/**
 * Get search results based on query parameters
 * 
 * @return array Query results and pagination data
 */
function get_search_results_jobs() {
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    
    $args = [
        'post_type' => 'lowongan',
        'posts_per_page' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
        'paged' => $paged
    ];

    // Search by keywords
    $keywords = isset($_GET['keywords']) ? sanitize_text_field($_GET['keywords']) : '';
    if (!empty($keywords)) {
        $args['s'] = $keywords;
    }

    // Build taxonomy query
    $tax_query = [];

    // Location filter
    $location = isset($_GET['loc']) ? sanitize_text_field($_GET['loc']) : '';
    if (!empty($location)) {
        $tax_query[] = [
            'taxonomy' => 'lokasi-pekerjaan',
            'field' => 'slug',
            'terms' => $location
        ];
    }

    // Experience filter
    $experience = isset($_GET['pengalaman']) ? sanitize_text_field($_GET['pengalaman']) : '';
    if (!empty($experience)) {
        $tax_query[] = [
            'taxonomy' => 'pengalaman',
            'field' => 'slug',
            'terms' => $experience
        ];
    }

    // Education filter
    $education = isset($_GET['pendidikan']) ? sanitize_text_field($_GET['pendidikan']) : '';
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

    return [
        'query' => $query,
        'max_pages' => $query->max_num_pages,
        'current_page' => $paged,
        'found_posts' => $query->found_posts
    ];
}

?>