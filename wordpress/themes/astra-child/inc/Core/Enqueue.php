<?php

namespace AstraChild\Core;

class Enqueue
{
    /**
     * Register scripts and styles.
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueJS']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyle']);
    }

    public function enqueueStyle(): void
    {
        wp_enqueue_style(
            'astra-child-tailwind',
            get_stylesheet_directory_uri() . '/assets/css/style.css',
            [],
            filemtime(get_stylesheet_directory() . '/assets/css/style.css')
        );
    }

    public function enqueueJS(): void
    {
        wp_enqueue_script(
            'alpinejs',
            get_stylesheet_directory_uri() . '/assets/js/dist/alpinejs/cdn.min.js',
            [],
            filemtime(get_stylesheet_directory() . '/assets/js/dist/alpinejs/cdn.min.js'),
            true
        );
        
        if (is_front_page() || is_post_type_archive('lowongan')) 
        {
            wp_enqueue_script(
                'dynamic-search',
                get_stylesheet_directory_uri() . '/assets/js/DynamicSearch.js',
                ['alpinejs'],
                filemtime(get_stylesheet_directory() . '/assets/js/DynamicSearch.js'),
                true
            );
            wp_enqueue_script(
                'auto-suggestion-search',
                get_stylesheet_directory_uri() . '/assets/js/AutoSuggestionSearch.js',
                ['alpinejs'],
                filemtime(get_stylesheet_directory() . '/assets/js/AutoSuggestionSearch.js'),
                true
            );
            wp_enqueue_script(
                'loadmore-jobs',
                get_stylesheet_directory_uri() . '/assets/js/LoadMore.js',
                ['alpinejs'],
                filemtime(get_stylesheet_directory() . '/assets/js/LoadMore.js'),
                true
            );
            wp_enqueue_script(
                'time-post',
                get_stylesheet_directory_uri() . '/assets/js/TimePost.js',
                ['alpinejs'],
                filemtime(get_stylesheet_directory() . '/assets/js/TimePost.js'),
                true
            );
            wp_enqueue_script(
                'select2',
                get_stylesheet_directory_uri() . '/assets/js/dist/select2/select2.min.js',
                ['jquery'],
                filemtime(get_stylesheet_directory() . '/assets/js/dist/select2/select2.min.js'),
                false
            );
            wp_enqueue_script(
                'select2-init',
                get_stylesheet_directory_uri() . '/assets/js/select2.js',
                ['jquery', 'select2'],
                filemtime(get_stylesheet_directory() . '/assets/js/select2.js'),
                true
            );
        }

        if (is_front_page()) 
        {
            wp_enqueue_script(
                'swiper',
                get_stylesheet_directory_uri() . '/assets/js/dist/swiper/swiper-bundle.min.js',
                [],
                filemtime(get_stylesheet_directory() . '/assets/js/dist/swiper/swiper-bundle.min.js'),
                true
            );
            wp_enqueue_script(
                'carousel-swiper',
                get_stylesheet_directory_uri() . '/assets/js/swiper.js',
                ['swiper'],
                filemtime(get_stylesheet_directory() . '/assets/js/swiper.js'),
                true
            );
        }
    }
}
