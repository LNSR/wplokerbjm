<?php

namespace AstraChild\Services\REST;


class RESTServices
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('astra-child/v1', '/auto-suggest/', [
            'methods'  => 'GET',
            'callback' => [\AstraChild\Controllers\REST\AutoSuggestionSearch::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('astra-child/v1', '/load-more/', [
            'methods'  => 'GET',
            'callback' => [\AstraChild\Controllers\REST\LoadMore::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('astra-child/v1', '/search/', [
            'methods'  => 'GET',
            'callback' => [\AstraChild\Controllers\REST\DynamicSearch::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('astra-child/v1', '/taxonomies/', [
            'methods'  => 'GET',
            'callback' => [\AstraChild\Controllers\REST\TaxonomyDepth::class, 'handle'],
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
                'callback' => [\AstraChild\Controllers\REST\TaxonomyDepth::class, $taxonomy],
                'permission_callback' => '__return_true',
            ]);
        }

        register_rest_route('astra-child/v1', '/carousel/', [
            'methods'  => 'GET',
            'callback' => [\AstraChild\Controllers\REST\Carousel::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }
}
