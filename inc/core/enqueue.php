<?php

// function my_theme_enqueue_styles()
// {
//     wp_enqueue_style('astra-theme-css', get_template_directory_uri() . '/style.css', array(), ASTRA_THEME_VERSION, 'all');
//     wp_enqueue_style('astra-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), wp_get_theme()->get('Version'), 'all');
// }
// add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles', 15);



// Enqueue the script

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

function enqueue_share_scripts() {
    if (is_singular('lowongan')) {
        wp_enqueue_script(
            'lowongan-share', 
            get_stylesheet_directory_uri() . '/assets/js/share.js', 
            ['jquery'],
            '1.0.0', 
            true
        );

        wp_localize_script('lowongan-share', 'lowonganAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lowongan_share_nonce')
        ]);
    }
}
add_action('wp_enqueue_scripts', 'enqueue_share_scripts');

function enqueue_status_carousel_scripts() {
    if (is_page_template('page-homepage.php')) {
        wp_enqueue_script(
            'status-carousel',
            get_stylesheet_directory_uri() . '/assets/js/status-carousel.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script(
            'status-carousel',
            'statusCarouselData',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('status_carousel_nonce')
            ]
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_status_carousel_scripts');
?>
