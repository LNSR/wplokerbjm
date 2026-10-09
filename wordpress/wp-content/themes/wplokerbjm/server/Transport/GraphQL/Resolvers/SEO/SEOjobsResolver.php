<?php

namespace WPLokerBJM\Transport\GraphQL\Resolvers\SEO;

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\Injectable;
use WPLokerBJM\Services\SEO\{RankMathHeadService, JobSchemaService};
use WPLokerBJM\Services\Schema\SEO\JobSchemaBuilder;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * @phpstan-import-type JobPostingSchema from JobSchemaBuilder
 * @phpstan-import-type ItemListSchema from JobSchemaBuilder
 * @phpstan-import-type JobSchemaArgs from GraphQLRegistration
 * @phpstan-import-type RankMathHeadArgs from GraphQLRegistration
 * @phpstan-import-type JobSchemaResponse from GraphQLRegistration
 */
#[Injectable(lazy: true)]
class SEOjobsResolver
{
    public function __construct(
        private RankMathHeadService $rankMathHeadService,
        private JobSchemaService $jobSchemaService,
    ) {}

    /**
     * Resolve RankMath head HTML for a given URL.
     *
     * Validates that the URL is internal to this site before fetching.
     *
     * @param mixed $root The root Query object (unused)
     * @param RankMathHeadArgs $args Arguments containing the URL
     * @return string HTML head tags from RankMath
     */
    public function resolveRankMathHead($root, $args): string
    {
        try {
            return $this->rankMathHeadService->getHead((string) ($args['url'] ?? ''));
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'SEOjobsResolver::resolveRankMathHead error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Resolve Schema.org JSON-LD data for jobs.
     *
     * Supports per-id JobPosting schemas or combined ItemList. Can also resolve
     * by slug to avoid an extra post ID lookup.
     *
     * @param mixed $root The root Query object (unused)
     * @param JobSchemaArgs $args Schema query arguments
     * @return JobSchemaResponse
     */
    public function resolveSchema($root, $args): array
    {
        try {
            return $this->jobSchemaService->resolveSchema($args);
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'SEOjobsResolver::resolveSchema error: ' . $e->getMessage());
            return ['schemas' => []];
        }
    }
}
