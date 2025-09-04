<?php
namespace AstraChild\Controllers\REST;

use AstraChild\Services\Taxonomy\TaxonomyService;
use AstraChild\Repositories\TaxonomyRepository;
class TaxonomyDepth {
    public function __construct(private TaxonomyService $service, private TaxonomyRepository $repository) {
    }

    public function handle(\WP_REST_Request $request) {
        try {

            $terms = $this->repository->getTaxonomyTerms();

            $response = [
                'lokasiTerms' => $this->service->buildTermsTree($terms['lokasi_terms']),
                'genderTerms' => array_values(array_map(fn($term) => [
                    'slug' => $term->slug,
                    'name' => $term->name
                ], $terms['gender_terms'])),
                'pendidikanTerms' => $this->service->buildTermsTree($terms['pendidikan_terms']),
            ];

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('TaxonomyDepth::handle error: ' . $e->getMessage());
            return rest_ensure_response([
                'lokasiTerms' => [],
                'genderTerms' => [],
                'pendidikanTerms' => []
            ]);
        }
    }

    public function lokasi(\WP_REST_Request $request) {
        try {

            $terms = $this->repository->getTaxonomyTerms();
            $response = $this->service->buildTermsTree($terms['lokasi_terms']);

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('TaxonomyDepth::lokasi error: ' . $e->getMessage());
            return rest_ensure_response([]);
        }
    }

    public function gender(\WP_REST_Request $request) {
        try {

            $terms = $this->repository->getTaxonomyTerms();
            $response = array_values(array_map(fn($term) => [
                'slug' => $term->slug,
                'name' => $term->name
            ], $terms['gender_terms']));

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('TaxonomyDepth::gender error: ' . $e->getMessage());
            return rest_ensure_response([]);
        }
    }

    public function pendidikan(\WP_REST_Request $request) {
        try {

            $terms = $this->repository->getTaxonomyTerms();
            $response = $this->service->buildTermsTree($terms['pendidikan_terms']);

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('TaxonomyDepth::pendidikan error: ' . $e->getMessage());
            return rest_ensure_response([]);
        }
    }
}