<?php
namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Repositories\TaxonomyRepository;
use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
class TaxonomyDepth
{
    public function __construct(private TaxonomyRepository $repository)
    {
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $cached = Cache::get(CacheKey::TAXONOMY_DEPTH_HANDLE);
            if ($cached !== false) {
                return rest_ensure_response($cached);
            }

            $terms = $this->repository->getTaxonomyTerms();

            $response = [
                'lokasiTerms' => ControllerUtils::buildTermsTree($terms[Taxonomies::LOKASI_PEKERJAAN]),
                'genderTerms' => array_values(array_map(fn($term) => [
                    'slug' => $term->slug,
                    'name' => $term->name,
                ], $terms[Taxonomies::GENDER])),
                'pendidikanTerms' => ControllerUtils::buildTermsTree($terms[Taxonomies::PENDIDIKAN]),
            ];

            Cache::set(CacheKey::TAXONOMY_DEPTH_HANDLE, $response);

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            Logger::error('REST', 'TaxonomyDepth::handle error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('Internal server error', 500);
        }
    }

    public function lokasi(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $cached = Cache::get(CacheKey::TAXONOMY_DEPTH_LOKASI);
            if ($cached !== false) {
                return rest_ensure_response($cached);
            }

            $terms = $this->repository->getTaxonomyTerms();
            $response = ControllerUtils::buildTermsTree($terms[Taxonomies::LOKASI_PEKERJAAN]);

            Cache::set(CacheKey::TAXONOMY_DEPTH_LOKASI, $response);

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            Logger::error('REST', 'TaxonomyDepth::lokasi error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('Internal server error', 500);
        }
    }

    public function gender(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $cached = Cache::get(CacheKey::TAXONOMY_DEPTH_GENDER);
            if ($cached !== false) {
                return rest_ensure_response($cached);
            }

            $terms = $this->repository->getTaxonomyTerms();
            $response = array_values(array_map(fn($term) => [
                'slug' => $term->slug,
                'name' => $term->name,
            ], $terms[Taxonomies::GENDER]));

            Cache::set(CacheKey::TAXONOMY_DEPTH_GENDER, $response);

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            Logger::error('REST', 'TaxonomyDepth::gender error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('Internal server error', 500);
        }
    }

    public function pendidikan(\WP_REST_Request $request)
    {
        try {
            $cached = Cache::get(CacheKey::TAXONOMY_DEPTH_PENDIDIKAN);
            if ($cached !== false) {
                return rest_ensure_response($cached);
            }

            $terms = $this->repository->getTaxonomyTerms();
            $response = ControllerUtils::buildTermsTree($terms[Taxonomies::PENDIDIKAN]);

            Cache::set(CacheKey::TAXONOMY_DEPTH_PENDIDIKAN, $response);

            return rest_ensure_response($response);
        } catch (\Exception $e) {
            Logger::error('REST', 'TaxonomyDepth::pendidikan error: ' . $e->getMessage());
            return ControllerUtils::failedResponse('Internal server error', 500);
        }
    }
}