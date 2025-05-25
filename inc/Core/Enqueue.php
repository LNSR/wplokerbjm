<?php

namespace AstraChild\Core;

class Enqueue
{
	/**
	 * Register scripts and styles.
	 */
	public function register(): void
	{
		add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
	}

	public function enqueueAssets(): void
	{
		wp_enqueue_script(
			'alpinejs',
			get_stylesheet_directory_uri() . '/assets/js/dist/alpinejs/cdn.min.js',
			[],
			filemtime(get_stylesheet_directory() . '/assets/js/dist/alpinejs/cdn.min.js'),
			args: false
		);
		wp_enqueue_style(
			'astra-child-tailwind',
			get_stylesheet_directory_uri() . '/assets/css/style.css',
			[],
			ver: filemtime(get_stylesheet_directory() . '/assets/css/style.css')
		);
		wp_enqueue_script(
			'astra-color-switch',
			get_stylesheet_directory_uri() . '/assets/js/AstraColorSwitch.js',
			[],
			filemtime(get_stylesheet_directory() . '/assets/js/AstraColorSwitch.js'),
			true
		);
		if (is_front_page() || is_post_type_archive('lowongan')) {

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

			// LoadMore Alpine component
			wp_enqueue_script(
				'loadmore-jobs',
				get_stylesheet_directory_uri() . '/assets/js/LoadMore.js',
				['alpinejs'],
				filemtime(get_stylesheet_directory() . '/assets/js/LoadMore.js'),
				false
			);
		}
		if (is_front_page()) {

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
