<?php

namespace AstraChild\Factories;

use AstraChild\Contracts\DataProviderInterface;
use AstraChild\Services\CustomField\CustomFieldsService;
use AstraChild\Services\Taxonomy\TaxonomyService;
use AstraChild\Services\CustomField\SocialMediaService;

class JobDataFactory
{
    public function __construct(
        protected ?DataProviderInterface $customFieldsProvider,
        protected ?DataProviderInterface $taxonomiesProvider,
        protected ?CustomFieldsService $customFieldsService,
        protected ?TaxonomyService $taxonomyService,
        protected ?SocialMediaService $socialMediaService
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
        $customFields = $this->customFieldsProvider?->getMetaBoxData($post_id) ?? [];
        $taxonomies = $this->taxonomiesProvider?->getMetaBoxData($post_id) ?? [];

        // Process custom fields
        $processedCustomFields = is_object($this->customFieldsService)
            ? $this->customFieldsService->processCustomFields((array) $customFields)
            : [];

        // Process taxonomies
        $processedTaxonomies = [];
        if (is_object($this->taxonomyService)) {
            foreach ((array) $taxonomies as $key => $terms) {
                $processedTerms = $this->taxonomyService->processTaxonomyTerms($terms);
                $processedTaxonomies[$key] = is_array($processedTerms) ? implode(', ', $processedTerms) : 'N/A';
            }
        }

        // Combine meta and taxonomy data
        $combinedData = array_merge($processedCustomFields, $processedTaxonomies);

        return $combinedData;
    }

    /**
     * Create social media data and prepare it for display
     *
     * @param array $socialMediaData Raw social media data
     * @return array Created social media items ready for display
     */
    public function createSocialMediaItems(array $socialMediaData): array
    {
        $processedItems = [];

        foreach ($socialMediaData as $platform => $usernames) {
            $usernames = is_array($usernames) ? $usernames : [$usernames];
            foreach ($usernames as $username) {
                if (empty($platform) || empty($username)) {
                    continue;
                }
                $linkData = $this->socialMediaService->getLinkData($platform, $username);
                if ($linkData) {
                    $processedItems[] = [
                        'platform' => $platform,
                        'username' => $username,
                        'icon' => $linkData['icon'],
                        'url' => $linkData['url'],
                    ];
                }
            }
        }

        return $processedItems;
    }
}
