<?php

namespace AstraChild\Services\REST;

use AstraChild\Contracts\HooksInterface;


class RESTRoute implements HooksInterface
{
    public static string $baseURI = 'astra-child/v1';

    public function __construct(
        private readonly \AstraChild\Controllers\REST\TaxonomyDepth $taxonomyDepth,
        private readonly \AstraChild\Controllers\REST\AutoSuggestionSearch $autoSuggestionSearch,
        private readonly \AstraChild\Controllers\REST\LoadMore $loadMore,
        private readonly \AstraChild\Controllers\REST\DynamicSearch $dynamicSearch,
        private readonly \AstraChild\Controllers\REST\SingleOverlay $singleOverlay,
        private readonly \AstraChild\Controllers\REST\DispatchSSGBuild $dispatchSSGBuild,
        private readonly \AstraChild\Controllers\REST\JobBookmark $jobBookmark
    ) {
    }

    public function registerActions(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }
    public function registerFilters(): void
    {
        add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
            if (!headers_sent()) {
                header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link, X-WP-Nonce');
            }
            return $served;
        }, 10, 4);
    }

    public function registerRoutes(): void
    {
        /** @see \AstraChild\Controllers\REST\AutoSuggestionSearch::handle() */
        register_rest_route(self::$baseURI, '/auto-suggest/', [
            'methods' => 'GET',
            'callback' => [$this->autoSuggestionSearch, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        /** @see \AstraChild\Controllers\REST\LoadMore::handle() */
        register_rest_route(self::$baseURI, '/load-more/', [
            'methods' => 'GET',
            'callback' => [$this->loadMore, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        /** @see \AstraChild\Controllers\REST\DynamicSearch::handle() */
        register_rest_route(self::$baseURI, '/search/', [
            'methods' => 'GET',
            'callback' => [$this->dynamicSearch, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        /** @see \AstraChild\Controllers\REST\TaxonomyDepth::handle() */
        register_rest_route(self::$baseURI, '/taxonomies/', [
            'methods' => 'GET',
            'callback' => [$this->taxonomyDepth, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        $taxonomies = [
            'lokasi',
            'gender',
            'pendidikan',
        ];
        foreach ($taxonomies as $taxonomy) {
            /** @see \AstraChild\Controllers\REST\TaxonomyDepth::handle() */
            register_rest_route(self::$baseURI, "/taxonomies/$taxonomy", [
                'methods' => 'GET',
                'callback' => [$this->taxonomyDepth, $taxonomy],
                'permission_callback' => '__return_true',
            ]);
        }

        /** @see \AstraChild\Controllers\REST\SingleOverlay::handle() */
        register_rest_route(self::$baseURI, '/single-overlay/', [
            'methods' => 'GET',
            'callback' => [$this->singleOverlay, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        /** @see \AstraChild\Controllers\REST\DispatchSSGBuild::handle() */
        register_rest_route(self::$baseURI, '/dispatch-ssg/', [
            'methods' => 'POST',
            'callback' => [$this->dispatchSSGBuild, 'handle'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => $this->dispatchSSGBuild->getRouteArgs()
        ]);

        /** @see \AstraChild\Controllers\REST\JobBookmark::handle() */
        register_rest_route(self::$baseURI, '/bookmarked-jobs/', [
            'methods' => 'GET',
            'callback' => [$this->jobBookmark, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }
}
