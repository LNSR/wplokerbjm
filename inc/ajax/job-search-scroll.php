<?php
function handle_job_search_scroll() {
    check_ajax_referer('job_search_scroll_nonce', '_ajax_nonce');

    // Set paged parameter from AJAX request
    $_GET = array_map('sanitize_text_field', $_POST);
    $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
    
    // Call the search function with the updated page number
    $search_results = get_search_results_jobs();
    $query = $search_results['query'];
    
    ob_start();
    
    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();
            get_template_part('template-parts/homepage/content-job-card');
        endwhile;
    endif;

    wp_reset_postdata();
    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html,
        'hasMore' => ($paged < $search_results['max_pages']),
        'maxPages' => $search_results['max_pages']
    ]);
}

add_action('wp_ajax_job_search_scroll', 'handle_job_search_scroll');
add_action('wp_ajax_nopriv_job_search_scroll', 'handle_job_search_scroll');