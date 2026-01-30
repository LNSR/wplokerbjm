<?php

namespace WPLokerBJM\Presenters\Pages;

use WPLokerBJM\Services\Schema\JobSchemaOrg;
use WPLokerBJM\Services\GraphQL\GraphQLData;
use WPLokerBJM\Presenters\SEO\Schema\JobPostingSchema;
use WPLokerBJM\Presenters\SEO\SkeletonHTML\SkeletonForSEO;

class SinglePresenter
{
    public function __construct(
        private JobSchemaOrg $jobSchema,
        private GraphQLData $restData,
    ) {
    }

    public function getProps(int $post_id): array
    {
        return [
            'job' => $this->restData->getJobDetailData($post_id)
        ];
    }

    public function getSingleData(int $post_id): array
    {
        $props = $this->getProps($post_id);

        $schema_data = $this->jobSchema->getJobPostingSchema($post_id);
        $schema = JobPostingSchema::renderSchemaJobPosting($schema_data, $post_id);

        $seoHtml = SkeletonForSEO::generateSEOHTML($props['job']);

        return [
            'props' => $props,
            'schema' => $schema,
            'seoHtml' => $seoHtml,
        ];
    }
}