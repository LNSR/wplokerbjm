<?php
namespace AstraChild\Core;

/**
 * WordPress Setup Manager
 * 
 * Handles WordPress configuration and initialization tasks
 */
class Setup
{
    /**
     * Initialize the setup manager
     */
    public function __construct()
    {
        // Theme setup
        add_action('after_setup_theme', [$this, 'themeSetup']);
        
        // Widget areas
        add_action('widgets_init', [$this, 'registerSidebars']);
        
        // Image sizes
        add_action('after_setup_theme', [$this, 'registerImageSizes']);
        
        // Excerpt modifications
        add_filter('excerpt_length', [$this, 'customExcerptLength']);
        add_filter('excerpt_more', [$this, 'customExcerptMore']);
        
        // Body class
        add_filter('body_class', [$this, 'addCustomBodyClasses']);
    }
    
    /**
     * Set up theme defaults and register support for various WordPress features
     */
    public function themeSetup(): void
    {
        // Add default posts and comments RSS feed links to head
        add_theme_support('automatic-feed-links');
        
        // Enable support for Post Thumbnails on posts and pages
        add_theme_support('post-thumbnails');
        
        // Enable support for title tag
        add_theme_support('title-tag');
        
        // Switch default core markup to output valid HTML5
        add_theme_support('html5', [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
        ]);
        
        // Register navigation menus
        register_nav_menus([
            'primary' => __('Primary Menu', 'astra-child'),
            'footer' => __('Footer Menu', 'astra-child'),
        ]);
    }
    
    /**
     * Register widget areas
     */
    public function registerSidebars(): void
    {
        register_sidebar([
            'name'          => __('Job Sidebar', 'astra-child'),
            'id'            => 'job-sidebar',
            'description'   => __('Widgets for job listings sidebar', 'astra-child'),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h4 class="widget-title">',
            'after_title'   => '</h4>',
        ]);
    }
    
    /**
     * Register custom image sizes
     */
    public function registerImageSizes(): void
    {
        add_image_size('job-thumbnail', 300, 200, true);
        add_image_size('company-logo', 150, 150, false);
    }
    
    /**
     * Modify excerpt length
     */
    public function customExcerptLength(): int
    {
        return 20; // Number of words
    }
    
    /**
     * Modify excerpt ending
     */
    public function customExcerptMore(): string
    {
        return '...';
    }
    
    /**
     * Add custom body classes
     */
    public function addCustomBodyClasses($classes): array
    {
        // Add page template name as class
        if (is_page_template()) {
            $template = str_replace('.php', '', get_page_template_slug());
            $template = str_replace('page-', '', $template);
            $classes[] = 'template-' . $template;
        }
        
        // Add specific class for job pages
        if (is_singular('lowongan')) {
            $classes[] = 'single-job-page';
        }
        
        return $classes;
    }
}