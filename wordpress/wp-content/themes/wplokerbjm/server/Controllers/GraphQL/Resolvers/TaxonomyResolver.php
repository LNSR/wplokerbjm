<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers;

use WPLokerBJM\Repositories\TaxonomyRepository;
use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Log\Logger;

/**
 * @phpstan-type TaxonomyTerms array{slug: string, name: string, parent: int, children: array}
 */
class TaxonomyResolver
{
    public function __construct(
        private TaxonomyRepository $repository
    ) {
    }

    const LOKASI_TERMS = 'lokasiTerms';
    const GENDER_TERMS = 'genderTerms';
    const PENDIDIKAN_TERMS = 'pendidikanTerms';

    /**
     * Resolve all taxonomy terms grouped by type.
     *
     * @return array{lokasiTerms: TaxonomyTerms[], genderTerms: TaxonomyTerms[], pendidikanTerms: TaxonomyTerms[]}
     */
    public function resolveAllTerms(): array
    {
        try {
            $cached = Cache::get(CacheKey::TAXONOMY_DEPTH_HANDLE);
            if ($cached !== false) {
                return $cached;
            }

            $terms = $this->repository->getTaxonomyTerms();

            $response = [
                self::LOKASI_TERMS => ControllerUtils::buildTermsTree($terms[Taxonomies::LOKASI_PEKERJAAN]),
                self::GENDER_TERMS => ControllerUtils::buildTermsTree($terms[Taxonomies::GENDER]),
                self::PENDIDIKAN_TERMS => ControllerUtils::buildTermsTree($terms[Taxonomies::PENDIDIKAN]),
            ];

            Cache::set(CacheKey::TAXONOMY_DEPTH_HANDLE, $response);

            return $response;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'TaxonomyResolver::resolveAllTerms error: ' . $e->getMessage());
            return [
                'lokasiTerms' => [],
                'genderTerms' => [],
                'pendidikanTerms' => [],
            ];
        }
    }

    /**
     * Resolve location taxonomy terms with hierarchy.
     *
     * @return TaxonomyTerms[] Tree structure of location terms
     */
    public function resolveLokasiTerms(): array
    {
        try {
            $cached = Cache::get(CacheKey::TAXONOMY_DEPTH_LOKASI);
            if ($cached !== false) {
                return $cached;
            }

            $terms = $this->repository->getTaxonomyTerms();
            $response = ControllerUtils::buildTermsTree($terms[Taxonomies::LOKASI_PEKERJAAN]);

            Cache::set(CacheKey::TAXONOMY_DEPTH_LOKASI, $response);

            return $response;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'TaxonomyResolver::resolveLokasiTerms error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Resolve gender taxonomy terms (flat list).
     *
     * @return TaxonomyTerms[]
     */
    public function resolveGenderTerms(): array
    {
        try {
            $cached = Cache::get(CacheKey::TAXONOMY_DEPTH_GENDER);
            if ($cached !== false) {
                return $cached;
            }

            $terms = $this->repository->getTaxonomyTerms();
            $response = ControllerUtils::buildTermsTree($terms[Taxonomies::GENDER]);

            Cache::set(CacheKey::TAXONOMY_DEPTH_GENDER, $response);

            return $response;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'TaxonomyResolver::resolveGenderTerms error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Resolve education level taxonomy terms with hierarchy.
     *
     * @return TaxonomyTerms[] Tree structure of pendidikan terms
     */
    public function resolvePendidikanTerms(): array
    {
        try {
            $cached = Cache::get(CacheKey::TAXONOMY_DEPTH_PENDIDIKAN);
            if ($cached !== false) {
                return $cached;
            }

            $terms = $this->repository->getTaxonomyTerms();
            $response = ControllerUtils::buildTermsTree($terms[Taxonomies::PENDIDIKAN]);

            Cache::set(CacheKey::TAXONOMY_DEPTH_PENDIDIKAN, $response);

            return $response;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'TaxonomyResolver::resolvePendidikanTerms error: ' . $e->getMessage());
            return [];
        }
    }
}