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
    '/inc/meta-box/post-types.php',         // Custom post type definitions
    '/inc/meta-box/taxonomies.php',         // Custom taxonomy definitions
    '/inc/meta-box/custom-fields.php',      // Custom field configurations
        // Meta Box Call
        '/inc/core/setup.php',

    '/inc/core/enqueue.php',                // Script and style enqueuing
    
    // Windpress files
    '/inc/windpress/scanner.php',           // WindPress Scanner
    
    // Helper Files
    '/inc/helpers/job-helpers.php',         // Job Helper
    '/inc/helpers/social-media.php',        // Social Media Helper
    
    // AJAX Handlers
    // '/inc/ajax/job-search.php',             // Job search AJAX handler
    '/inc/ajax/status-carousel.php',        // Homepage slider AJAX handler
    '/inc/ajax/share.php',                  // Share job AJAX handler
    '/inc/ajax/featured-jobs.php',          // Featured jobs AJAX handler
    '/inc/ajax/job-search-scroll.php',      // Infinite search AJAX handler
];

// Include all defined files
foreach ($include_files as $file) {
    require_once get_stylesheet_directory() . $file;
}


// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'astra-theme-css' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION
