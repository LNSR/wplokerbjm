<?php

namespace AstraChild\Services\REST;

use AstraChild\Contracts\HooksInterface;


class RESTRoute implements HooksInterface
{
 
    public function __construct(
        private \AstraChild\Controllers\REST\TaxonomyDepth $taxonomyDepth,
        private \AstraChild\Controllers\REST\AutoSuggestionSearch $autoSuggestionSearch,
        private \AstraChild\Controllers\REST\LoadMore $loadMore,
        private \AstraChild\Controllers\REST\DynamicSearch $dynamicSearch,
        private \AstraChild\Controllers\REST\Carousel $carousel,
        private \AstraChild\Controllers\REST\SingleOverlay $singleOverlay
    )
    {
    }

    public function registerActions(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }
    public function registerFilters(): void
    {
        // No filters to register in this class
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
