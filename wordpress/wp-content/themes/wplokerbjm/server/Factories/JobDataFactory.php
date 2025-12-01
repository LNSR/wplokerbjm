<?php

namespace WPLokerBJM\Factories;

use WPLokerBJM\Contracts\DataProviderInterface;
use WPLokerBJM\Core\ObjectCache;

class JobDataFactory
{
    const FACTORY_JOB_PREFIX = 'job_data_';

    public function __construct(
        private DataProviderInterface $customFieldsProvider,
        private DataProviderInterface $taxonomiesProvider,
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
        $cacheKey = self::FACTORY_JOB_PREFIX . $post_id;
        $cachedData = ObjectCache::get($cacheKey);
        if ($cachedData !== false) {
            return $cachedData;
        }

        try {
            $customFields = $this->customFieldsProvider?->getMetaBoxData($post_id) ?? [];
            $taxonomies = $this->taxonomiesProvider?->getMetaBoxData($post_id) ?? [];

            $processedCustomFields = $this->processCustomFields($customFields);

            $processedTaxonomies = [];
            foreach ($taxonomies as $key => $terms) {
                $processedTerms = $this->processTaxonomyTerms($terms);
                $processedTaxonomies[$key] = is_array($processedTerms) ? implode(', ', $processedTerms) : 'N/A';
            }

            // Combine meta and taxonomy data
            $combinedData = array_merge($processedCustomFields, $processedTaxonomies);

            ObjectCache::set($cacheKey, $combinedData, 86400); // Cache for 1 day

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
            $wysiwyg_fields = ['tentang_perusahaan', 'deskripsi_pekerjaan', 'persyaratan', 'cara_melamar', 'benefit'];
            foreach ($wysiwyg_fields as $field) {
                if (!empty($customFields[$field])) {
                    // Accept strings only; other types are ignored
                    if (is_string($customFields[$field])) {
                        $customFields[$field] = do_shortcode(wpautop(wp_kses_post($customFields[$field])));
                    }
                }
            }

            // Process number fields
            $number_fields = ['umur_min', 'umur_max', 'pengalaman', 'gaji_minimal', 'gaji_maksimal'];
            foreach ($number_fields as $field) {
                if (!empty($customFields[$field]) && is_numeric($customFields[$field])) {
                    // Cast numeric strings or numbers to int
                    $customFields[$field] = (int) $customFields[$field];
                }
            }

            // Process email, URL, and text fields (handle arrays for cloned fields)
            foreach (['email_kontak', 'situs_kontak', 'nomor_kontak'] as $field) {
                if (!empty($customFields[$field])) {
                    if (is_array($customFields[$field])) {
                        $customFields[$field] = array_map(function ($value) use ($field) {
                            if ($field === 'email_kontak') {
                                return is_string($value) ? sanitize_email($value) : '';
                            }
                            if ($field === 'situs_kontak') {
                                return is_string($value) ? esc_url($value) : '';
                            }
                            if ($field === 'nomor_kontak') {
                                return is_string($value) ? sanitize_text_field($value) : '';
                            }
                            return $value;
                        }, $customFields[$field]);
                    } else {
                        if ($field === 'email_kontak' && is_string($customFields[$field])) {
                            $customFields[$field] = sanitize_email($customFields[$field]);
                        }
                        if ($field === 'situs_kontak' && is_string($customFields[$field])) {
                            $customFields[$field] = esc_url($customFields[$field]);
                        }
                        if ($field === 'nomor_kontak' && is_string($customFields[$field])) {
                            $customFields[$field] = sanitize_text_field($customFields[$field]);
                        }
                    }
                }
            }

            // Process date fields
            if (!empty($customFields['deadline']) && is_string($customFields['deadline'])) {
                $customFields['deadline'] = date('Y-m-d', strtotime($customFields['deadline']));
            }

            // Process fieldset fields (e.g., social media)
            if (!empty($customFields['social_media'])) {
                $socialMediaData = $customFields['social_media'];

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

                $customFields['social_media'] = $processedSocialMedia;
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