<?php

namespace AstraChild\Services\REST;

use AstraChild\Controllers\REST\AutoSuggestionSearch;
use AstraChild\Controllers\REST\Carousel;
use AstraChild\Controllers\REST\DynamicSearch;
use AstraChild\Controllers\REST\LoadMore;
use AstraChild\Controllers\REST\TaxonomyDepth;
use AstraChild\Controllers\REST\SingleOverlay;


class RESTRoute
{
 
    public function __construct(
        private TaxonomyDepth $taxonomyDepth,
        private AutoSuggestionSearch $autoSuggestionSearch,
        private LoadMore $loadMore,
        private DynamicSearch $dynamicSearch,
        private Carousel $carousel,
        private SingleOverlay $singleOverlay
    )
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('astra-child/v1', '/auto-suggest/', [
            'methods'  => 'GET',
            'callback' => [$this->autoSuggestionSearch, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('astra-child/v1', '/load-more/', [
            'methods'  => 'GET',
            'callback' => [$this->loadMore, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('astra-child/v1', '/search/', [
            'methods'  => 'GET',
            'callback' => [$this->dynamicSearch, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('astra-child/v1', '/taxonomies/', [
            'methods'  => 'GET',
            'callback' => [$this->taxonomyDepth, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        $taxonomies = [
            'lokasi',
            'gender',
            'pendidikan',
        ];
        foreach ($taxonomies as $taxonomy) {
            register_rest_route('astra-child/v1', "/taxonomies/$taxonomy", [
                'methods'  => 'GET',
                'callback' => [$this->taxonomyDepth, $taxonomy],
                'permission_callback' => '__return_true',
            ]);
        }

        register_rest_route('astra-child/v1', '/carousel/', [
            'methods'  => 'GET',
            'callback' => [$this->carousel, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('astra-child/v1', '/single-overlay/', [
            'methods'  => 'GET',
            'callback' => [$this->singleOverlay, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }
}
