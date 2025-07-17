<?php
namespace AstraChild\Controllers\REST;

use AstraChild\Services\Taxonomy\TaxonomyService;
use AstraChild\Repositories\TaxonomyRepository;

class TaxonomyDepth {
    public function __construct(private TaxonomyService $service, private TaxonomyRepository $repository) {
    }

    public function handle(\WP_REST_Request $request) {
        $terms = $this->repository->getTaxonomyTerms();

        return rest_ensure_response([
            'lokasiTerms' => $this->service->buildTermsTree($terms['lokasi_terms']),
            'genderTerms' => array_values(array_map(fn($term) => [
                'slug' => $term->slug,
                'name' => $term->name
            ], $terms['gender_terms'])),
            'pendidikanTerms' => $this->service->buildTermsTree($terms['pendidikan_terms']),
        ]);
    }

    public function lokasi(\WP_REST_Request $request) {
        $terms = $this->repository->getTaxonomyTerms();
        return rest_ensure_response($this->service->buildTermsTree($terms['lokasi_terms']));
    }

    public function gender(\WP_REST_Request $request) {
        $terms = $this->repository->getTaxonomyTerms();
        return rest_ensure_response(array_values(array_map(fn($term) => [
            'slug' => $term->slug,
            'name' => $term->name
        ], $terms['gender_terms'])));
    }

    public function pendidikan(\WP_REST_Request $request) {
        $terms = $this->repository->getTaxonomyTerms();
        return rest_ensure_response($this->service->buildTermsTree($terms['pendidikan_terms']));
    }
}