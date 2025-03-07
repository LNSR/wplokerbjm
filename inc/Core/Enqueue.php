<?php
namespace AstraChild\Core;

/**
 * Asset Enqueue Manager
 * 
 * Handles registration and enqueueing of stylesheets and scripts
 */
class Enqueue
{
    /**
     * Initialize the enqueue manager
     */
    public function __construct()
    {
        // Register scripts and styles hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueFeaturedJobsScripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueShareScripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueStatusCarouselScripts']);
    }

    /**
     * Enqueue featured jobs scripts
     * 
     * @return void
     */
    public function enqueueFeaturedJobsScripts(): void
    {
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

    /**
     * Enqueue share functionality scripts
     * 
     * @return void
     */
    public function enqueueShareScripts(): void
    {
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

    /**
     * Enqueue status carousel scripts
     * 
     * @return void
     */
    public function enqueueStatusCarouselScripts(): void
    {
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
}