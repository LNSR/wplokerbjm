<?php

namespace AstraChild\Services\REST;

use AstraChild\Contracts\HooksInterface;


class RESTRoute implements HooksInterface
{

    public function __construct(
        private readonly \AstraChild\Controllers\REST\TaxonomyDepth $taxonomyDepth,
        private readonly \AstraChild\Controllers\REST\AutoSuggestionSearch $autoSuggestionSearch,
        private readonly \AstraChild\Controllers\REST\LoadMore $loadMore,
        private readonly \AstraChild\Controllers\REST\DynamicSearch $dynamicSearch,
        private readonly \AstraChild\Controllers\REST\Carousel $carousel,
        private readonly \AstraChild\Controllers\REST\SingleOverlay $singleOverlay,
        private readonly \AstraChild\Controllers\REST\DispatchSSGBuild $dispatchSSGBuild
    ) {
    }

    public function registerActions(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }
    public function registerFilters(): void
    {
        // No filters to register in this class
    }

    /**
     * Arguments configuration for the /dispatch-ssg/ REST route.
     *
     * Extracted to a single method to keep registerRoutes() concise and
     * allow reuse or testing of the args definition.
     *
     * @return array
     */
    private function getDispatchSSGArgs(): array
    {
        // Deprecated: moved to DispatchSSGBuild::getRouteArgs() so the handler owns its route contract.
        return [];
    }

    public function registerRoutes(): void
    {
        /** @see \AstraChild\Controllers\REST\AutoSuggestionSearch::handle() */
        register_rest_route('astra-child/v1', '/auto-suggest/', [
            'methods' => 'GET',
            'callback' => [$this->autoSuggestionSearch, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        /** @see \AstraChild\Controllers\REST\LoadMore::handle() */
        register_rest_route('astra-child/v1', '/load-more/', [
            'methods' => 'GET',
            'callback' => [$this->loadMore, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        /** @see \AstraChild\Controllers\REST\DynamicSearch::handle() */
        register_rest_route('astra-child/v1', '/search/', [
            'methods' => 'GET',
            'callback' => [$this->dynamicSearch, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        /** @see \AstraChild\Controllers\REST\TaxonomyDepth::handle() */
        register_rest_route('astra-child/v1', '/taxonomies/', [
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
            register_rest_route('astra-child/v1', "/taxonomies/$taxonomy", [
                'methods' => 'GET',
                'callback' => [$this->taxonomyDepth, $taxonomy],
                'permission_callback' => '__return_true',
            ]);
        }

        /** @see \AstraChild\Controllers\REST\Carousel::handle() */
        register_rest_route('astra-child/v1', '/carousel/', [
            'methods' => 'GET',
            'callback' => [$this->carousel, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        /** @see \AstraChild\Controllers\REST\SingleOverlay::handle() */
        register_rest_route('astra-child/v1', '/single-overlay/', [
            'methods' => 'GET',
            'callback' => [$this->singleOverlay, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        /** @see \AstraChild\Controllers\REST\DispatchSSGBuild::handle() */
        register_rest_route('astra-child/v1', '/dispatch-ssg/', [
            'methods' => 'POST',
            'callback' => [$this->dispatchSSGBuild, 'handle'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => $this->dispatchSSGBuild->getRouteArgs()
        ]);
    }
}
