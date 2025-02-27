<?php
function load_status_carousel() {
    check_ajax_referer('status_carousel_nonce', '_ajax_nonce');

    // Use status IDs that match your meta values
    $status_priority = ['4', '3', '2'];
    $output = [];
    
    foreach ($status_priority as $status) {
        $args = [
            'post_type' => 'lowongan',
            'posts_per_page' => 1,
            'meta_key' => 'status_pekerjaan',
            'meta_value' => $status,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $job_data = get_job_meta_data();
                
                // Get all status attributes at once instead of three separate function calls
                $status_attrs = get_job_status_attributes($status);
                
                // Generate border class based on text color class
                $border_class = !empty($status_attrs['class']) ? 
                    ' border-' . substr(strstr($status_attrs['class'], 'text-'), 5, -4) . '-200' : '';
                
                $output[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'company' => $job_data['company'],
                    'location' => $job_data['location'],
                    'status' => [
                        'label' => $status_attrs['label'],
                        'icon' => $status_attrs['icon'],
                        'class' => $status_attrs['class'] . $border_class
                    ]
                ];
            }
        }
        wp_reset_postdata();
    }
    
    // If we don't have 3 items, add some normal jobs to fill the carousel
    if (count($output) < 3) {
        $args = [
            'post_type' => 'lowongan',
            'posts_per_page' => 3 - count($output),
            'meta_key' => 'status_pekerjaan',
            'meta_value' => '0', // Normal jobs
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $job_data = get_job_meta_data();
                
                $output[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'company' => $job_data['company'],
                    'location' => $job_data['location'],
                    'status' => [
                        'label' => 'Recommended',
                        'icon' => 'fas fa-thumbs-up',
                        'class' => 'bg-blue-100 text-blue-800 border-blue-200'
                    ]
                ];
            }
        }
        wp_reset_postdata();
    }
    
    wp_send_json_success($output);
}

add_action('wp_ajax_load_status_carousel', 'load_status_carousel');
add_action('wp_ajax_nopriv_load_status_carousel', 'load_status_carousel');