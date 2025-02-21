<?php
function load_featured_jobs() {
    check_ajax_referer('featured_jobs_nonce', '_ajax_nonce');

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $featured_jobs = get_featured_jobs_data($page);
    
    ob_start();

    if ($featured_jobs['query']->have_posts()) {
        while ($featured_jobs['query']->have_posts()) {
            $featured_jobs['query']->the_post();
            get_template_part('template-parts/homepage/content-job-card');
        }
    } else {
        echo '<p class="text-gray-500 text-center">Tidak ada lowongan tersedia.</p>';
    }

    wp_reset_postdata();
    
    wp_send_json_success([
        'html' => ob_get_clean(),
        'found_posts' => $featured_jobs['found_posts'],
        'max_pages' => $featured_jobs['max_pages']
    ]);
}

add_action('wp_ajax_load_featured_jobs', 'load_featured_jobs');
add_action('wp_ajax_nopriv_load_featured_jobs', 'load_featured_jobs');

?>