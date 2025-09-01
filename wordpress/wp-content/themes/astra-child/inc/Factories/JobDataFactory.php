<?php

namespace AstraChild\Factories;

use AstraChild\Contracts\DataProviderInterface;
use AstraChild\Services\CustomField\CustomFieldsService;
use AstraChild\Services\Taxonomy\TaxonomyService;
use AstraChild\Core\Cache;

class JobDataFactory
{
    public function __construct(
        private DataProviderInterface $customFieldsProvider,
        private DataProviderInterface $taxonomiesProvider,
        private CustomFieldsService $customFieldsService,
        private TaxonomyService $taxonomyService
    ) {}

    /**
     * Process and combine custom fields and taxonomy data for a job listing.
     *
     * This method retrieves custom fields and taxonomy data for the given post ID,
     * processes the data (e.g., sanitization, formatting), and combines it into
     * a single associative array for use in views or other parts of the application.
     *
     * @param int $post_id Post ID
     * @return array Combined and processed job data
     */
    public function buatDataPekerjaan(int $post_id): array
    {
        $combinedData = Cache::get('job_data_factory_' . $post_id);
        if ($combinedData !== false) {
            return $combinedData;
        }

        $customFields = $this->customFieldsProvider?->getMetaBoxData($post_id) ?? [];
        $taxonomies = $this->taxonomiesProvider?->getMetaBoxData($post_id) ?? [];

        // Process custom fields
        $processedCustomFields = is_object($this->customFieldsService)
            ? $this->customFieldsService->processCustomFields((array) $customFields)
            : [];

        // Process taxonomies
        $processedTaxonomies = [];
        if (is_object($this->taxonomyService)) {
            foreach ($taxonomies as $key => $terms) {
                $processedTerms = $this->taxonomyService->processTaxonomyTerms($terms);
                $processedTaxonomies[$key] = is_array($processedTerms) ? implode(', ', $processedTerms) : 'N/A';
            }
        }

        // Combine meta and taxonomy data
        $combinedData = array_merge($processedCustomFields, $processedTaxonomies);

        // Cache for 1 day
        Cache::set('job_data_' . $post_id, $combinedData, 86400);

        return $combinedData;
    }
}
