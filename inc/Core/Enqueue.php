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
        add_action('wp_enqueue_scripts', [$this, 'enqueueCarouselStyles'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAstraChildStyle']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueJobSearchScrollScripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueJobArchiveScripts']);
    }

    /**
     * Enqueue featured jobs scripts
     * 
     * @return void
     */
    public function enqueueFeaturedJobsScripts(): void
    {
        // Only load on homepage or pages that might have featured jobs
        if (is_front_page() || is_home() || has_shortcode(get_post()->post_content ?? '', 'featured_jobs')) {
            wp_enqueue_script(
                'featured-jobs',
                get_stylesheet_directory_uri() . '/assets/js/featured-jobs.js',
                ['jquery'],
                filemtime(get_stylesheet_directory() . '/assets/js/featured-jobs.js'),
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
                    'action' => 'load_status_carousel', // Add this line
                    'nonce' => wp_create_nonce('status_carousel_nonce')
                ]
            );
        }
    }

    /**
     * Enqueue carousel styles
     * 
     * @return void
     */
    public function enqueueCarouselStyles(): void
    {
        wp_add_inline_style('theme-style', '
            /* Base carousel styling */
            .status-carousel-item {
                transition: all 0.3s ease;
                flex: 0 0 calc(100% / 3); /* Default for 3 items */
                max-width: calc(100% / 3 - 1rem); /* Account for gap */
                padding: 0 0.5rem;
            }
            
            #status-carousel {
                margin: 0 -0.5rem; /* Offset item padding */
                display: flex;
                flex-wrap: nowrap;
                transition: transform 0.3s ease;
            }
            
            /* Responsive behavior */
            @media (max-width: 768px) {
                .status-carousel-item {
                    flex: 0 0 100%;
                    max-width: calc(100% - 1rem);
                }
            }
            
            @media (min-width: 769px) and (max-width: 1024px) {
                .status-carousel-item {
                    flex: 0 0 50%;
                    max-width: calc(50% - 1rem);
                }
            }
        ');
    }

    /**
     * Enqueue Astra child theme style
     * 
     * @return void
     */
    public function enqueueAstraChildStyle(): void
    {
        wp_enqueue_style( 
            'astra-child-style', 
            get_stylesheet_uri(), 
            array(), 
            filemtime(get_stylesheet_directory() . '/style.css')  // Use file modification time as version
        );
    }

    /**
     * Enqueue job search scroll scripts
     * 
     * @return void
     */
    public function enqueueJobSearchScrollScripts(): void
    {
        // Check for page template OR specific URL path
        $is_search_page = is_page_template('page-search-jobs.php') || 
                          (is_page() && (strpos($_SERVER['REQUEST_URI'], '/search-jobs/') !== false));
        
        if ($is_search_page) {
            wp_enqueue_script(
                'job-search-scroll',
                get_stylesheet_directory_uri() . '/assets/js/job-search-scroll.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script(
                'job-search-scroll',
                'jobSearchScrollData',
                [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('job_search_scroll_nonce')
                ]
            );
        }
    }

    /**
     * Enqueue job archive scripts
     * 
     * @return void
     */
    public function enqueueJobArchiveScripts(): void
    {
        // Only load on the lowongan archive page
        if (is_post_type_archive('lowongan') || is_tax('lokasi-pekerjaan') || is_tax('jenis-pekerjaan') || is_tax('pengalaman') || is_tax('pendidikan') || is_tax('gender') || is_tax('gaji')) {
            wp_enqueue_script(
                'archive-jobs',
                get_stylesheet_directory_uri() . '/assets/js/archive-jobs.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script(
                'archive-jobs',
                'archiveJobsData',
                [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('archive_jobs_nonce')
                ]
            );
        }
    }
}