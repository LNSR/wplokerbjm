<?php

namespace AstraChild\Repositories;

use AstraChild\Factories\JobDataFactory;

/**
 * Job Repository
 * 
 * Provides methods to interact with job meta data using Meta Box functions
 */
class JobRepository
{
    public function __construct(
        private JobDataFactory $jobDataFactory,
    ) {}

    /**
     * Combined Taxonomies and Meta data for a job data
     *
     * @param int $post_id Post ID
     * @return array Combined job data
     */
    public function getJobData(int $post_id): array
    {
        return $this->jobDataFactory->buatDataPekerjaan($post_id);
    }
}
