<?php

namespace WPLokerBJM\Repositories;
use WPLokerBJM\Services\GraphQL\GraphQLJobData;
use WPLokerBJM\Services\Schema\JobSchemaOrg;
/**
 * Job Repository
 * 
 * Provides methods to interact with job meta data using Meta Box functions
 * @phpstan-import-type CardData from GraphQLJobData
 * @phpstan-import-type JobPostingSchema from JobSchemaOrg
 */
class JobRepository
{
    public function __construct(
        private GraphQLJobData $restData,
        private JobSchemaOrg $jobSchema
    ) {
    }

    /**
     * Run a WP_Query and return normalized card data and schema.
     *
     * @param array $query_args WP_Query args
     * @return array{jobs: CardData[], schema_data: JobPostingSchema[], query: \WP_Query}
     */
    public function queryJob(array $query_args): array
    {

        $jobs_query = new \WP_Query($query_args);

        $jobs = [];
        $schema_data = [];

        if ($jobs_query->have_posts()) {
            while ($jobs_query->have_posts()) {
                $jobs_query->the_post();
                (int) $post_id = get_the_ID();
                $jobs[] = $this->restData->getCardData($post_id);
                $schema_data[] = $this->jobSchema->getJobPostingSchema($post_id);
            }
            wp_reset_postdata();
        }

        $result = [
            'jobs' => $jobs,
            'query' => $jobs_query,
            'schema_data' => $schema_data,
        ];

        return $result;
    }
}
