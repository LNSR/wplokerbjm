<?php

namespace WPLokerBJM\Presenters\Pages;

use WPLokerBJM\Services\Schema\JobSchemaOrg;
use WPLokerBJM\Services\REST\RESTData;
use WPLokerBJM\Presenters\Schema\JobPostingSchema;

class SinglePresenter
{
    public function __construct(
        private JobSchemaOrg $jobSchema,
        private RESTData $restData
    ) {
    }

    public function getSingleData(int $post_id): array
    {
        $props = [
            'job' => $this->restData->getSingleOverlayData($post_id)
        ];

        $schema_data = $this->jobSchema->getJobPostingSchema($post_id);
        $schema = JobPostingSchema::renderSchema($schema_data, $post_id);

        return [
            'props' => $props,
            'schema' => $schema,
        ];
    }
}