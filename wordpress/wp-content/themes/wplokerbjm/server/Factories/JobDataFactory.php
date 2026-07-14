<?php

namespace WPLokerBJM\Factories;

use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
/**
 *  @phpstan-type JobData array{
 *     nama_perusahaan?: string,
 *     tentang_perusahaan?: string|null,
 *     deskripsi_pekerjaan?: string|null,
 *     persyaratan?: string|null,
 *     cara_melamar?: string|null,
 *     benefit?: string|null,
 *     umur_min?: int,
 *     umur_max?: int,
 *     pengalaman?: int,
 *     gaji_minimal?: int,
 *     gaji_maksimal?: int,
 *     deadline?: string,
 *     email_kontak?: string,
 *     nomor_kontak?: string,
 *     situs_kontak?: string,
 *     social_media?: string,
 *     status_pekerjaan?: int,
 *     perusahaan?: string,
 *     kategori_lowongan?: string,
 *     lokasi_pekerjaan?: string,
 *     jenis_pekerjaan?: string,
 *     gender?: string,
 *     pendidikan?: string,
 * }
 */
class JobDataFactory
{
    public function __construct(
        private \WPLokerBJM\Repositories\CustomFieldRepository $customFieldRepository,
        private \WPLokerBJM\Repositories\TaxonomyRepository $taxonomyRepository
    ) {
    }

    private const WYSIWYG_FIELDS = [
        CustomFields::TENTANG_PERUSAHAAN,
        CustomFields::DESKRIPSI_PEKERJAAN,
        CustomFields::PERSYARATAN,
        CustomFields::CARA_MELAMAR,
        CustomFields::BENEFIT,
    ];
    private const NUMBER_FIELDS = [
        CustomFields::UMUR_MIN,
        CustomFields::UMUR_MAX,
        CustomFields::PENGALAMAN,
        CustomFields::GAJI_MINIMAL,
        CustomFields::GAJI_MAKSIMAL,
        CustomFields::STATUS_PEKERJAAN,
    ];
    private const CONTACT_FIELDS = [
        CustomFields::EMAIL_KONTAK,
        CustomFields::SITUS_KONTAK,
        CustomFields::NOMOR_KONTAK,
    ];

    /**
     * Process and combine custom fields and taxonomy data for a job listing.
     *
     * This method retrieves custom fields and taxonomy data for the given post ID,
     * processes the data (e.g., sanitization, formatting), and combines it into
     * a single associative array for use in views or other parts of the application.
     * @param int $post_id Post ID
     * @return JobData Combined and processed job data
     */
    public function createJobData(int $post_id): array
    {
        $cacheKey = CacheKey::JOB_DATA_PREFIX . $post_id;
        /** @var JobData|false $cachedData */
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

            // Filter out null values to keep responses lean
            $combinedData = SharedUtils::filterEmptyValues($combinedData);

            Cache::set($cacheKey, $combinedData, 86400); // Cache for 1 day

            return $combinedData;
        } catch (\Exception $e) {
            Logger::error('Factory', 'JobDataFactory::createJobData error for post ' . $post_id . ': ' . $e->getMessage());
            return []; // Return empty array on error
        }
    }

    /**
     * Process custom fields data.
     *
     * Applies sanitization and formatting to various field types:
     * - WYSIWYG fields: wpautop, wp_kses_post, do_shortcode
     * - Number fields: cast to int
     * - Contact fields: sanitize_email, esc_url, sanitize_text_field
     * - Date fields: format to Y-m-d
     * - Social media: flatten and sanitize nested arrays
     *
     * @param array<string, mixed> $customFields Raw custom fields data from Meta Box
     * @return array<string, mixed> Processed custom fields data with null for invalid values
     */
    private function processCustomFields(array $customFields): array
    {
        try {
            // Process WYSIWYG fields
            $wysiwyg_fields = self::WYSIWYG_FIELDS;
            foreach ($wysiwyg_fields as $field) {
                if (isset($customFields[$field])) {
                    // Accept strings only; other types are ignored
                    if (is_string($customFields[$field])) {
                        $customFields[$field] = do_shortcode(wpautop(wp_kses_post($customFields[$field])));
                    } else {
                        $customFields[$field] = null;
                    }
                }
            }

            // Process number fields
            $number_fields = self::NUMBER_FIELDS;
            foreach ($number_fields as $field) {
                if (isset($customFields[$field])) {
                    if (is_numeric($customFields[$field])) {
                        // Cast numeric strings or numbers to int
                        $customFields[$field] = (int) $customFields[$field];
                    } else {
                        $customFields[$field] = null;
                    }
                }
            }

            $sanitize_contact_fields = [
                self::CONTACT_FIELDS[0] => static fn($v) => is_string($v) ? (sanitize_email($v) ?: null) : null,
                self::CONTACT_FIELDS[1] => static fn($v) => is_string($v) ? (esc_url($v) ?: null) : null,
                self::CONTACT_FIELDS[2] => static fn($v) => is_string($v) ? (sanitize_text_field($v) ?: null) : null,
            ];

            // Process email, URL, and text fields (handle arrays for cloned fields)
            foreach (self::CONTACT_FIELDS as $field) {
                if (isset($customFields[$field])) {
                    $sanitize_callback = $sanitize_contact_fields[$field];
                    if (is_array($customFields[$field])) {
                        $customFields[$field] = array_map($sanitize_callback, $customFields[$field])
                            |> array_filter(...)
                            |> (static fn($arr) => implode(', ', $arr));
                    } else {
                        $customFields[$field] = $sanitize_callback($customFields[$field]);
                    }
                }
            }

            // Process date fields
            if (isset($customFields[CustomFields::DEADLINE])) {
                if (is_string($customFields[CustomFields::DEADLINE]) && strtotime($customFields[CustomFields::DEADLINE]) !== false) {
                    $customFields[CustomFields::DEADLINE] = date('Y-m-d', strtotime($customFields[CustomFields::DEADLINE]));
                } else {
                    $customFields[CustomFields::DEADLINE] = null;
                }
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

                // pipe operator version
                $customFields[CustomFields::SOCIAL_MEDIA] = array_map(
                    static fn($platform, $usernames) => $platform . ': ' . implode(', ', $usernames),
                    array_keys($processedSocialMedia),
                    $processedSocialMedia
                )
                    |> (static fn($v) => implode('; ', $v));
            }
        } catch (\Exception $e) {
            Logger::error('Factory', 'CustomFieldsService::processCustomFields error: ' . $e->getMessage());
        } finally {
            return $customFields;
        }
    }
    /**
     * Process taxonomy terms into sanitized name strings.
     *
     * Handles WP_Term objects, associative arrays, and plain strings.
     *
     * @param array<int, \WP_Term|array{name: string}|string> $terms Raw taxonomy terms
     * @return array<int, string> Sanitized term names
     */
    private function processTaxonomyTerms(array $terms): array
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