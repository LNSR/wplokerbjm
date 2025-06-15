<?php
namespace AstraChild\Controllers\REST;

use AstraChild\Services\Taxonomy\TaxonomyService;
use AstraChild\Core\Container;
use AstraChild\Repositories\TaxonomyRepository;

class TaxonomyDepth {
    public static function handle(\WP_REST_Request $request) {
        /** @var TaxonomyService */
        $service = Container::getContainer()->get(TaxonomyService::class);
        /** @var TaxonomyRepository */
        $repository = Container::getContainer()->get(TaxonomyRepository::class);

        $terms = $repository->getTaxonomyTerms();

        return rest_ensure_response([
            'lokasiTerms' => $service->buildTermsTree($terms['lokasi_terms']),
            'genderTerms' => array_values(array_map(fn($term) => [
                'slug' => $term->slug,
                'name' => $term->name
            ], $terms['gender_terms'])),
            'pendidikanTerms' => $service->buildTermsTree($terms['pendidikan_terms']),
        ]);
    }

    public static function lokasi(\WP_REST_Request $request) {
        $service = Container::getContainer()->get(TaxonomyService::class);
        $repository = Container::getContainer()->get(TaxonomyRepository::class);
        $terms = $repository->getTaxonomyTerms();
        return rest_ensure_response($service->buildTermsTree($terms['lokasi_terms']));
    }

    public static function gender(\WP_REST_Request $request) {
        $repository = Container::getContainer()->get(TaxonomyRepository::class);
        $terms = $repository->getTaxonomyTerms();
        return rest_ensure_response(array_values(array_map(fn($term) => [
            'slug' => $term->slug,
            'name' => $term->name
        ], $terms['gender_terms'])));
    }

    public static function pendidikan(\WP_REST_Request $request) {
        $service = Container::getContainer()->get(TaxonomyService::class);
        $repository = Container::getContainer()->get(TaxonomyRepository::class);
        $terms = $repository->getTaxonomyTerms();
        return rest_ensure_response($service->buildTermsTree($terms['pendidikan_terms']));
    }
}