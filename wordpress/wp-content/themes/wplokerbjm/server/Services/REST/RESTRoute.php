<?php

namespace WPLokerBJM\Services\REST;

use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Core\Container\Attributes\Action;


class RESTRoute
{
    public static string $baseURI = 'wplokerbjm/v1';

    public function __construct(
        private readonly \WPLokerBJM\Controllers\REST\TaxonomyDepth $taxonomyDepth,
        private readonly \WPLokerBJM\Controllers\REST\AutoSuggestionSearch $autoSuggestionSearch,
        private readonly \WPLokerBJM\Controllers\REST\LoadMore $loadMore,
        private readonly \WPLokerBJM\Controllers\REST\Carousel $carousel,
        private readonly \WPLokerBJM\Controllers\REST\DynamicSearch $dynamicSearch,
        private readonly \WPLokerBJM\Controllers\REST\JobDetail $singleOverlay,
        private readonly \WPLokerBJM\Controllers\REST\JobBookmark $jobBookmark,
        private readonly \WPLokerBJM\Controllers\REST\JobGridController $jobGrid,
        private readonly \WPLokerBJM\Controllers\REST\WPThemeData $wpThemeData,
        private readonly \WPLokerBJM\Controllers\REST\JobSchemaController $jobSchema
    ) {
    }

    #[Action('rest_api_init', 0)]
    public function registerRoutes(): void
    {
        register_rest_route(self::$baseURI, '/auto-suggest/', [
            'methods' => 'GET',
            'callback' => fn(...$args) => $this->autoSuggestionSearch->handle(...$args),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::$baseURI, '/load-more/', [
            'methods' => 'GET',
            'callback' => fn(...$args) => $this->loadMore->handle(...$args),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::$baseURI, '/search/', [
            'methods' => 'GET',
            'callback' => fn(...$args) => $this->dynamicSearch->handle(...$args),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::$baseURI, '/taxonomies/', [
            'methods' => 'GET',
            'callback' => fn(...$args) => $this->taxonomyDepth->handle(...$args),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::$baseURI, '/carousel/', [
            'methods' => 'GET',
            'callback' => fn(...$args) => $this->carousel->handle(...$args),
            'permission_callback' => '__return_true',
        ]);

        $taxonomies = [
            Taxonomies::LOKASI_PEKERJAAN => fn(...$args) => $this->taxonomyDepth->lokasi(...$args),
            Taxonomies::GENDER => fn(...$args) => $this->taxonomyDepth->gender(...$args),
            Taxonomies::PENDIDIKAN => fn(...$args) => $this->taxonomyDepth->pendidikan(...$args),
        ]; // explictly define method to ensure IDE can reference properly

        foreach ($taxonomies as $taxonomy => $callback) {
            register_rest_route(self::$baseURI, "/taxonomies/$taxonomy", [
                'methods' => 'GET',
                'callback' => $callback,
                'permission_callback' => '__return_true',
            ]);
        }

        register_rest_route(self::$baseURI, '/job-detail/', [
            'methods' => 'GET',
            'callback' => fn(...$args) => $this->singleOverlay->handle(...$args),
            'permission_callback' => '__return_true',
        ]);

        // POST is used for bookmark sync to avoid URL length limits with many IDs.
        // GET would put IDs in query string (?ids=1,2,3,...), which can exceed ~2000 chars on mobile.
        // POST sends IDs in JSON body, allowing unlimited bookmarks without 414 errors.
        register_rest_route(self::$baseURI, '/bookmarked-jobs/', [
            'methods' => 'POST',
            'callback' => fn(...$args) => $this->jobBookmark->handle(...$args),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::$baseURI, '/job-grid/', [
            'methods' => 'GET',
            'callback' => fn(...$args) => $this->jobGrid->handle(...$args),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::$baseURI, '/theme-data/', [
            'methods' => 'GET',
            'callback' => fn(...$args) => $this->wpThemeData->handle(...$args),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::$baseURI, '/job-schema/', [
            'methods' => 'GET',
            'callback' => fn(...$args) => $this->jobSchema->handle(...$args),
            'permission_callback' => '__return_true',
        ]);
    }
}
