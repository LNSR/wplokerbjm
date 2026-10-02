<?php

namespace WPLokerBJM\Transport\GraphQL\Registration\Job\SEO;

use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;
use WPLokerBJM\Transport\GraphQL\Resolvers\SEO\SEOjobsResolver;

class JobSchemaRegistrationTypes
{
    public const TYPE_JOB_SCHEMA_RESPONSE = 'JobSchemaResponse';
    public function __construct(private readonly SEOjobsResolver $seoJobsResolver) {}

    public function __invoke(): void
    {
        $this->registerObjectTypes();
        $this->registerInputTypes();
        $this->registerRootFields();
    }

    private function registerObjectTypes(): void
    {
        register_graphql_object_type(self::TYPE_JOB_SCHEMA_RESPONSE, [
            'description' => 'Job schema response',
            'fields' => [
                'schemas' => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
            ],
        ]);
    }

    private function registerInputTypes(): void {}
    private function registerRootFields(): void
    {

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'jobSchema', [
            'type' => self::TYPE_JOB_SCHEMA_RESPONSE,
            'description' => 'Get job schema. Returns per-id JobPosting schemas by default (even for multiple IDs). Set `type` to "ItemList" to explicitly request an ItemList, or "JobPosting" to request per-id JobPosting schemas. You can also request schema by `slug` to avoid an extra lookup for the post ID.',
            'args' => [
                'ids' => [
                    'type' => ['list_of' => GraphQLRegistration::TYPE_INT],
                    'description' => 'Job IDs to retrieve. By default the resolver returns per-id JobPosting schemas; set `type` to "ItemList" to request a combined ItemList for these IDs.',
                ],
                'slug' => [
                    'type' => GraphQLRegistration::TYPE_STRING,
                    'description' => 'Optional job slug. When provided, schema is generated for the job matching this slug.',
                ],
                'type' => [
                    'type' => GraphQLRegistration::TYPE_STRING,
                    'description' => 'Optional schema type. Allowed values: "ItemList" (returns a single ItemList) or "JobPosting" (returns per-id JobPosting schemas). Defaults to per-id JobPosting behavior when omitted.',
                ],
            ],
            'resolve' => $this->seoJobsResolver->resolveSchema(...),
        ]);

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'rankMathHead', [
            'type' => GraphQLRegistration::TYPE_STRING,
            'description' => 'Get RankMath head data',
            'args' => [
                'url' => [
                    'type' => GraphQLRegistration::TYPE_STRING,
                    'description' => 'URL for RankMath',
                ],
            ],
            'resolve' => $this->seoJobsResolver->resolveRankMathHead(...),
        ]);
    }
}
