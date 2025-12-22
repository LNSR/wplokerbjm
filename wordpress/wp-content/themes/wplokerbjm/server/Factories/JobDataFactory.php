<?php

namespace WPLokerBJM\Factories;

use WPLokerBJM\Core\Cache;
use WPLokerBJM\Models\Schema\CustomFields;

class JobDataFactory
{
    const FACTORY_JOB_PREFIX_CACHE = 'job_data_';
    const FACTORY_JOB_TTL_CACHE = 86400; // 1 day

    public function __construct(
        private \WPLokerBJM\Repositories\CustomFieldRepository $customFieldRepository,
        private \WPLokerBJM\Repositories\TaxonomyRepository $taxonomyRepository
    ) {
    }

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
    public function createJobData(int $post_id): array
    {
        $cacheKey = self::FACTORY_JOB_PREFIX_CACHE . $post_id;
        $cachedData = Cache::get($cacheKey);
        if ($cachedData !== false) {
            return $cachedData;
        }

        try {
            $customFields = $this->customFieldRepository->getMetaBoxCustomFields($post_id) ?? [];
            $taxonomies = $this->taxonomyRepository->getMetaBoxTaxonomies($post_id) ?? [];

            $processedCustomFields = $this->processCustomFields($customFields);

            $processedTaxonomies = [];
            foreach ($taxonomies as $key => $terms) {
                $processedTerms = $this->processTaxonomyTerms($terms);
                $processedTaxonomies[$key] = is_array($processedTerms) ? implode(', ', $processedTerms) : 'N/A';
            }

            // Combine meta and taxonomy data
            $combinedData = array_merge($processedCustomFields, $processedTaxonomies);

            Cache::set($cacheKey, $combinedData, self::FACTORY_JOB_TTL_CACHE); // Cache for 1 day

            return $combinedData;
        } catch (\Exception $e) {
            error_log('JobDataFactory::createJobData error for post ' . $post_id . ': ' . $e->getMessage());
            return []; // Return empty array on error
        }
    }

    /**
     * Process custom fields data.
     *
     * @param array $customFields Raw custom fields data.
     * @return array Processed custom fields data.
     */
    public function processCustomFields(array $customFields): array
    {
        try {
            // Process WYSIWYG fields
            $wysiwyg_fields = [CustomFields::TENTANG_PERUSAHAAN, CustomFields::DESKRIPSI_PEKERJAAN, CustomFields::PERSYARATAN, CustomFields::CARA_MELAMAR, CustomFields::BENEFIT];
            foreach ($wysiwyg_fields as $field) {
                if (!empty($customFields[$field])) {
                    // Accept strings only; other types are ignored
                    if (is_string($customFields[$field])) {
                        $customFields[$field] = do_shortcode(wpautop(wp_kses_post($customFields[$field])));
                    }
                }
            }

            // Process number fields
            $number_fields = [CustomFields::UMUR_MIN, CustomFields::UMUR_MAX, CustomFields::PENGALAMAN, CustomFields::GAJI_MINIMAL, CustomFields::GAJI_MAKSIMAL, CustomFields::STATUS_PEKERJAAN];
            foreach ($number_fields as $field) {
                if (!empty($customFields[$field]) && is_numeric($customFields[$field])) {
                    // Cast numeric strings or numbers to int
                    $customFields[$field] = (int) $customFields[$field];
                }
            }

            $sanitize_contact_fields = [
                CustomFields::EMAIL_KONTAK => fn($v) => is_string($v) ? sanitize_email($v) : null,
                CustomFields::SITUS_KONTAK => fn($v) => is_string($v) ? esc_url($v) : null,
                CustomFields::NOMOR_KONTAK => fn($v) => is_string($v) ? sanitize_text_field($v) : null,
            ];

            // Process email, URL, and text fields (handle arrays for cloned fields)
            foreach ([CustomFields::EMAIL_KONTAK, CustomFields::SITUS_KONTAK, CustomFields::NOMOR_KONTAK] as $field) {
                $sanitize_callback = $sanitize_contact_fields[$field];
                if (!empty($customFields[$field]) && is_array($customFields[$field])) {
                    array_filter(array_map($sanitize_callback, $customFields[$field]));
                } else {
                    $customFields[$field] = $sanitize_callback($customFields[$field]);
                }
            }

            // Process date fields
            if (!empty($customFields[CustomFields::DEADLINE]) && is_string($customFields[CustomFields::DEADLINE])) {
                $customFields[CustomFields::DEADLINE] = date('Y-m-d', strtotime($customFields[CustomFields::DEADLINE]));
            }

            // Process fieldset fields (e.g., social media)
            if (!empty($customFields[CustomFields::SOCIAL_MEDIA])) {
                $socialMediaData = $customFields[CustomFields::SOCIAL_MEDIA];

                // Meta Box sometimes stores serialized arrays; handle string (serialized or json) and arrays
                if (is_string($socialMediaData)) {
                    // Try json first, then attempt to unserialize
                    $decoded = json_decode($socialMediaData, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $socialMediaData = $decoded;
                    } else {
                        $maybeUnserialized = @unserialize($socialMediaData);
                        if ($maybeUnserialized !== false && is_array($maybeUnserialized)) {
                            $socialMediaData = $maybeUnserialized;
                        }
                    }
                }

                $processedSocialMedia = [];

                // Flatten all sets and keep only non-empty usernames
                if (is_array($socialMediaData)) {
                    foreach ($socialMediaData as $platformSet) {
                        if (!is_array($platformSet)) {
                            continue;
                        }
                        foreach ($platformSet as $platform => $username) {
                            if (!is_string($platform) || (!is_string($username) && !is_numeric($username))) {
                                continue;
                            }
                            $platform = sanitize_text_field(trim($platform));
                            $username = sanitize_text_field(trim((string) $username));
                            if ($platform === '' || $username === '') {
                                continue;
                            }
                            $processedSocialMedia[$platform][] = $username;
                        }
                    }
                }

                $customFields[CustomFields::SOCIAL_MEDIA] = $processedSocialMedia;
            }
        } catch (\Exception $e) {
            error_log('CustomFieldsService::processCustomFields error: ' . $e->getMessage());
        } finally {
            return $customFields;
        }
    }
    /**
     * Process taxonomy terms.
     *
     * @param array $terms Raw taxonomy terms (array of term objects/arrays/strings).
     * @return array Processed taxonomy term names.
     */
    public function processTaxonomyTerms(array $terms): array
    {
        if (empty($terms)) {
            return [];
        }

        $names = [];
        foreach ($terms as $term) {
            if ($term instanceof \WP_Term && isset($term->name)) {
                $names[] = sanitize_text_field($term->name);
                continue;
            }
            if (is_array($term) && isset($term['name'])) {
                $names[] = sanitize_text_field($term['name']);
                continue;
            }
            if (is_string($term) && $term !== '') {
                $names[] = sanitize_text_field($term);
            }
        }

        return $names;
    }
}