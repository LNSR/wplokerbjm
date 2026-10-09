<?php

namespace WPLokerBJM\Transport\GraphQL\Resolvers;

use WPLokerBJM\Repositories\TaxonomyRepository;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;
use WPLokerBJM\Core\Container\Attributes\Injectable;;

/**
 * @phpstan-import-type TaxonomyTerms from GraphQLRegistration
 * @phpstan-import-type TaxonomyJobTerms from GraphQLRegistration
 */
#[Injectable(lazy: true)]
class TaxonomyResolver
{
    public function __construct(
        private TaxonomyRepository $repository
    ) {}


    /**
     * Resolve all taxonomy terms grouped by type.
     *
     * @return TaxonomyJobTerms
     */
    public function resolveAllTerms(): array
    {
        try {
            /** @var TaxonomyJobTerms|false $cached */
            $cached = Cache::get(CacheKey::TAXONOMY_DEPTH_HANDLE);
            if ($cached !== false) {
                return $cached;
            }

            $terms = $this->repository->getTaxonomyTerms();
            /** @var TaxonomyJobTerms $response */
            $response = [
                'lokasiTerms' => $this->buildTermsTree($terms[Taxonomies::LOKASI_PEKERJAAN]),
                'genderTerms' => $this->buildTermsTree($terms[Taxonomies::GENDER]),
                'pendidikanTerms' => $this->buildTermsTree($terms[Taxonomies::PENDIDIKAN]),
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
            $response = $this->buildTermsTree($terms[Taxonomies::LOKASI_PEKERJAAN]);

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
            $response = $this->buildTermsTree($terms[Taxonomies::GENDER]);

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
            $response = $this->buildTermsTree($terms[Taxonomies::PENDIDIKAN]);

            Cache::set(CacheKey::TAXONOMY_DEPTH_PENDIDIKAN, $response);

            return $response;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'TaxonomyResolver::resolvePendidikanTerms error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Build a hierarchical tree from WP_Term objects.
     *
     * @template T of \WP_Term
     * @param T[] $terms
     * @return list<array{slug: string, name: string, parent: int, children: list<array{slug: string, name: string, parent: int, children: list<mixed>}>}>
     */
    private function buildTermsTree(array $terms, $taxonomy = ''): array
    {
        try {
            $terms_by_id = [];
            foreach ($terms as &$term) {
                $terms_by_id[$term->term_id] = [
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'parent' => $term->parent,
                    'children' => [],
                ];
            }
            $tree = [];
            foreach ($terms_by_id as &$term) {
                if ($term['parent'] && isset($terms_by_id[$term['parent']])) {
                    $terms_by_id[$term['parent']]['children'][] = &$term;
                } else {
                    $tree[] = &$term;
                }
            }
            unset($term);

            return $tree;
        } catch (\Exception $e) {
            Logger::error('Taxonomy', 'TaxonomyService::buildTermsTree error: ' . $e->getMessage());
            return [];
        }
    }
}
