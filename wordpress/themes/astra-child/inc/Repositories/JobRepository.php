<?php

namespace AstraChild\Repositories;


/**
 * Job Repository
 * 
 * Provides methods to interact with job meta data using Meta Box functions
 */
class JobRepository
{
    public function __construct(
        private \AstraChild\Factories\JobDataFactory $jobDataFactory,
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
