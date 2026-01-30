<?php

namespace WPLokerBJM\Presenters\Pages;

use WPLokerBJM\Presenters\Components\{JobGrid, JobCarousel};
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Presenters\Schema\JobPostingSchema;
use WPLokerBJM\Services\GraphQL\GraphQLData;
use WPLokerBJM\Services\Schema\JobSchemaOrg;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};

class HomepagePresenter
{
    public function __construct(
        private JobGrid $jobGrid,
        private JobCarousel $jobCarousel,
        private JobRepository $jobRepository,
        private GraphQLData $graphqlData,
    ) {
    }

    public function getHomepageData(): array
    {
        $query_args = JobQuery::latestJobsArgs(1, 54);
        $query_result = $this->jobRepository->queryJob($query_args);

        $props = [
            'carousel' => $this->jobCarousel->getProps(),
            'jobGrid' => $this->jobGrid->getProps($query_args, 'Lowongan Terbaru', 'latest'),
        ];

        // sidepanel job detail for desktop view
        if (!wp_is_mobile() && get_the_ID() !== 146) {
            $props['job'] = $this->graphqlData->getJobDetailData(post_id: get_the_ID());
            $single_schema = $this->graphqlData->JobSchema(get_the_ID());
        }

        $post_ids = [];
        foreach ($query_result['jobs'] as $job) {
            $post_ids[] = $job['id'];
        }

        if (get_the_ID() === 146 || is_front_page()) {
            $itemListSchema = $this->graphqlData->ItemListJobPostings($post_ids);
            $schema = JobPostingSchema::renderSchemaItemList($itemListSchema);
        } else {
            $schema = JobPostingSchema::renderSchemaJobPosting($single_schema, get_the_ID());
        }

        return [
            'props' => $props,
            'schema' => $schema,
        ];
    }
}