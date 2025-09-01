<?php
namespace AstraChild\Controllers\REST;

use AstraChild\Services\Taxonomy\TaxonomyService;
use AstraChild\Repositories\TaxonomyRepository;
use AstraChild\Core\Cache;

class TaxonomyDepth {
    public function __construct(private TaxonomyService $service, private TaxonomyRepository $repository) {
    }

    public function handle(\WP_REST_Request $request) {
        $cacheKey = 'taxonomy_depth_all';

        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $terms = $this->repository->getTaxonomyTerms();

        $response = [
            'lokasiTerms' => $this->service->buildTermsTree($terms['lokasi_terms']),
            'genderTerms' => array_values(array_map(fn($term) => [
                'slug' => $term->slug,
                'name' => $term->name
            ], $terms['gender_terms'])),
            'pendidikanTerms' => $this->service->buildTermsTree($terms['pendidikan_terms']),
        ];

        // Cache for 24 hours
        Cache::set($cacheKey, $response, 86400);

        return rest_ensure_response($response);
    }

    public function lokasi(\WP_REST_Request $request) {
        $cacheKey = 'taxonomy_depth_lokasi';

        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $terms = $this->repository->getTaxonomyTerms();
        $response = $this->service->buildTermsTree($terms['lokasi_terms']);

        // Cache for 24 hours
        Cache::set($cacheKey, $response, 86400);

        return rest_ensure_response($response);
    }

    public function gender(\WP_REST_Request $request) {
        $cacheKey = 'taxonomy_depth_gender';

        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $terms = $this->repository->getTaxonomyTerms();
        $response = array_values(array_map(fn($term) => [
            'slug' => $term->slug,
            'name' => $term->name
        ], $terms['gender_terms']));

        // Cache for 24 hours
        Cache::set($cacheKey, $response, 86400);

        return rest_ensure_response($response);
    }

    public function pendidikan(\WP_REST_Request $request) {
        $cacheKey = 'taxonomy_depth_pendidikan';

        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $terms = $this->repository->getTaxonomyTerms();
        $response = $this->service->buildTermsTree($terms['pendidikan_terms']);

        // Cache for 24 hours
        Cache::set($cacheKey, $response, 86400);

        return rest_ensure_response($response);
    }
}