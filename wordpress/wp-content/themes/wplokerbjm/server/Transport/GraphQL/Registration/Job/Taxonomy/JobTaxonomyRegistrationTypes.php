<?php

namespace WPLokerBJM\Transport\GraphQL\Registration\Job\Taxonomy;

use TaxonomyJobTerms;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;
use WPLokerBJM\Transport\GraphQL\Resolvers\TaxonomyResolver;
/**
 * @phpstan-import-type TaxonomyJobTerms from GraphQLRegistration
 * @phpstan-import-type TaxonomyTerms from GraphQLRegistration
 */
class JobTaxonomyRegistrationTypes
{
    public function __construct(private readonly TaxonomyResolver $taxonomyResolver) {}

    private const TYPE_TAXONOMY_TERMS_RESPONSE = 'TaxonomyTermsResponse';
    private const TYPE_LOKASI_TERMS = 'lokasiTerms';
    private const TYPE_GENDER_TERMS = 'genderTerms';
    private const TYPE_PENDIDIKAN_TERMS = 'pendidikanTerms';

    public function __invoke(): void
    {
        $this->registerObjectTypes();
        $this->registerRootFields();
    }

    public function registerObjectTypes(): void
    {
        /** @var TaxonomyJobTerms $taxonomyJobFieldsType */
        $taxonomyJobFieldsType = [
            'lokasiTerms' => ['type' => GraphQLRegistration::TYPE_JSON],
            'genderTerms' => ['type' => GraphQLRegistration::TYPE_JSON],
            'pendidikanTerms' => ['type' => GraphQLRegistration::TYPE_JSON],
        ];
        
        // TaxonomyTermsResponse for grouped terms
        register_graphql_object_type(self::TYPE_TAXONOMY_TERMS_RESPONSE, [
            'description' => 'Response containing taxonomy terms',
            'fields' => $taxonomyJobFieldsType,
        ]);
    }

    public function registerRootFields(): void
    {
        // Root queries for taxonomy endpoints
        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'taxonomyTerms', [
            'type' => self::TYPE_TAXONOMY_TERMS_RESPONSE,
            'description' => 'Get all taxonomy terms grouped by type',
            'resolve' => $this->taxonomyResolver->resolveAllTerms(...),
        ]);

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, self::TYPE_LOKASI_TERMS, [
            'type' => GraphQLRegistration::TYPE_JSON,
            'description' => 'Get location taxonomy terms',
            'resolve' => $this->taxonomyResolver->resolveLokasiTerms(...),
        ]);

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, self::TYPE_GENDER_TERMS, [
            'type' => GraphQLRegistration::TYPE_JSON,
            'description' => 'Get gender taxonomy terms',
            'resolve' => $this->taxonomyResolver->resolveGenderTerms(...),
        ]);

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, self::TYPE_PENDIDIKAN_TERMS, [
            'type' => GraphQLRegistration::TYPE_JSON,
            'description' => 'Get education taxonomy terms',
            'resolve' => $this->taxonomyResolver->resolvePendidikanTerms(...),
        ]);
    }
}
