<?php

namespace WPLokerBJM\Transport\GraphQL\Registration\Job\SEO;

use JobSchemaResponse;
use RankMathHeadArgs;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;
use WPLokerBJM\Transport\GraphQL\Resolvers\SEO\SEOjobsResolver;
use WPLokerBJM\Transport\GraphQL\Registration\Theme\GraphQLThemeRegistrationTypes;

/**
 * @phpstan-import-type JobSchemaArgs from GraphQLRegistration
 * @phpstan-import-type RankMathHeadArgs from GraphQLRegistration
 * @phpstan-import-type JobSchemaResponse from GraphQLRegistration
 */
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
        /** @var JobSchemaResponse $jobSchemaManual */
        $jobSchemaManual = [
            'schemas' => ['type' => ['list_of' => GraphQLRegistration::TYPE_STRING]],
        ];
        register_graphql_object_type(self::TYPE_JOB_SCHEMA_RESPONSE, [
            'description' => 'Job schema response',
            'fields' => $jobSchemaManual,
        ]);
    }

    private function registerInputTypes(): void {}
    private function registerRootFields(): void
    {

        /** @var JobSchemaArgs $jobSchemaArgs */
        $jobSchemaArgs = [
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
        ];

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'jobSchema', [
            'type' => self::TYPE_JOB_SCHEMA_RESPONSE,
            'description' => 'Get job schema. Returns per-id JobPosting schemas by default (even for multiple IDs). Set `type` to "ItemList" to explicitly request an ItemList, or "JobPosting" to request per-id JobPosting schemas. You can also request schema by `slug` to avoid an extra lookup for the post ID.',
            'args' => $jobSchemaArgs,
            'resolve' => $this->seoJobsResolver->resolveSchema(...),
        ]);
        /** @var RankMathHeadArgs $rankMathHeadArgs */
        $rankMathHeadArgs = [
            'url' => [
                'type' => GraphQLRegistration::TYPE_STRING,
                'description' => 'URL for RankMath',
            ],
        ];

        register_graphql_field(GraphQLRegistration::TYPE_ROOT_QUERY, 'rankMathHead', [
            'type' => GraphQLRegistration::TYPE_STRING,
            'description' => 'Get RankMath head data',
            'args' => $rankMathHeadArgs,
            'resolve' => $this->seoJobsResolver->resolveRankMathHead(...),
        ]);
    }
}
