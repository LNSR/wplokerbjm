<?php

namespace WPLokerBJM\Repositories;

/**
 * Job Repository
 * 
 * Provides methods to interact with job meta data using Meta Box functions
 */
class JobRepository
{
    public function __construct(
        private \WPLokerBJM\Services\REST\RESTData $restData,
        private \WPLokerBJM\Services\Job\JobServices $jobServices
    ) {
    }


    /**
     * Run a WP_Query and return normalized card data and schema.
     *
     * @param array $query_args WP_Query args
     * @return array{jobs: array, schema: array, query: \WP_Query}
     */
    public function queryCard(array $query_args): array
    {

        $jobs_query = new \WP_Query($query_args);

        $jobs = [];
        $schema = [];

        if ($jobs_query->have_posts()) {
            while ($jobs_query->have_posts()) {
                $jobs_query->the_post();
                $post_id = get_the_ID();
                $jobs[] = $this->restData->getCardData($post_id);
                $schema[] = $this->jobServices->renderJobPostingJsonLd($post_id);
            }
            wp_reset_postdata();
        }

        $result = [
            'jobs' => $jobs,
            'query' => $jobs_query,
            'schema' => $schema,
        ];

        return $result;
    }
}
