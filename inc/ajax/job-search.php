<?php
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
        $location_terms = get_term_children($location, 'lokasi-pekerjaan');
        $location_terms[] = $location; // Include the parent term
        $tax_query[] = [
            'taxonomy' => 'lokasi-pekerjaan',
            'field' => 'slug',
            'terms' => $location_terms
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
        $education_terms = get_term_children($education, 'pendidikan');
        $education_terms[] = $education; // Include the parent term
        $tax_query[] = [
            'taxonomy' => 'pendidikan',
            'field' => 'slug',
            'terms' => $education_terms
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
                // Update this line to match the homepage path
                get_template_part('template-parts/homepage/content-job-card');
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

?>