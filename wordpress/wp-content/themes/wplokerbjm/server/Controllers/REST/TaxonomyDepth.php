<?php
namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Services\Taxonomy\TaxonomyService;
use WPLokerBJM\Repositories\TaxonomyRepository;
use WPLokerBJM\Services\Utilities\Utilities;
use WPLokerBJM\Models\Schema\Taxonomies;
class TaxonomyDepth {
    public function __construct(private TaxonomyService $service, private TaxonomyRepository $repository) {
    }

    public function handle(\WP_REST_Request $request) {
        try {

            $terms = $this->repository->getTaxonomyTerms();

            $response = [
                'lokasiTerms' => $this->service->buildTermsTree($terms[Taxonomies::LOKASI_PEKERJAAN]),
                'genderTerms' => array_values(array_map(fn($term) => [
                    'slug' => $term->slug,
                    'name' => $term->name
                ], $terms[Taxonomies::GENDER])),
                'pendidikanTerms' => $this->service->buildTermsTree($terms[Taxonomies::PENDIDIKAN]),
            ];

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('TaxonomyDepth::handle error: ' . $e->getMessage());
            return Utilities::failedResponse('Internal server error', 500);
        }
    }

    public function lokasi(\WP_REST_Request $request) {
        try {

            $terms = $this->repository->getTaxonomyTerms();
            $response = $this->service->buildTermsTree($terms[Taxonomies::LOKASI_PEKERJAAN]);

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('TaxonomyDepth::lokasi error: ' . $e->getMessage());
            return Utilities::failedResponse('Internal server error', 500);
        }
    }

    public function gender(\WP_REST_Request $request) {
        try {

            $terms = $this->repository->getTaxonomyTerms();
            $response = array_values(array_map(fn($term) => [
                'slug' => $term->slug,
                'name' => $term->name
            ], $terms[Taxonomies::GENDER]));

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('TaxonomyDepth::gender error: ' . $e->getMessage());
            return Utilities::failedResponse('Internal server error', 500);
        }
    }

    public function pendidikan(\WP_REST_Request $request) {
        try {

            $terms = $this->repository->getTaxonomyTerms();
            $response = $this->service->buildTermsTree($terms[Taxonomies::PENDIDIKAN]);

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            error_log('TaxonomyDepth::pendidikan error: ' . $e->getMessage());
            return Utilities::failedResponse('Internal server error', 500);
        }
    }
}