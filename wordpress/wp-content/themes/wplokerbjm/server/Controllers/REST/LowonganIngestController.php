<?php

declare(strict_types=1);

namespace WPLokerBJM\Controllers\REST;

use WPLokerBJM\Controllers\Utilities\ControllerUtils;
use WPLokerBJM\QueryBuilders\TaxonomyQuery;
use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Log\Logger;

/**
 * @phpstan-type IngestErrorResult array{status: 400|500, data: array{code: string, message: string, warnings: array}}
 * @phpstan-type IngestDuplicateResult array{status: 409, data: array{code: string, message: string, existing_id: int, warnings: array}}
 * @phpstan-type IngestSuccessResult array{status: 201, data: array{id: int, status: string, edit_url: string, permalink: string, warnings: array}}
 * @phpstan-type IngestResult IngestErrorResult|IngestDuplicateResult|IngestSuccessResult
 */
class LowonganIngestController
{
    private const LOG_CATEGORY = 'LowonganIngest';
    private const HASH_META_KEY = '_wplokerbjm_ingest_hash';
    private const SOURCE_META_KEY = '_wplokerbjm_ingest_source';

    private const WYSIWYG_FIELDS = [
        CustomFields::TENTANG_PERUSAHAAN,
        CustomFields::DESKRIPSI_PEKERJAAN,
        CustomFields::PERSYARATAN,
        CustomFields::CARA_MELAMAR,
        CustomFields::BENEFIT,
    ];

    private const TEXT_FIELDS = [
        CustomFields::NAMA_PERUSAHAAN,
    ];

    private const CONTACT_FIELDS = [
        CustomFields::EMAIL_KONTAK,
        CustomFields::NOMOR_KONTAK,
        CustomFields::SITUS_KONTAK,
    ];

    private const INT_FIELDS = [
        CustomFields::UMUR_MIN,
        CustomFields::UMUR_MAX,
        CustomFields::PENGALAMAN,
        CustomFields::GAJI_MINIMAL,
        CustomFields::GAJI_MAKSIMAL,
        CustomFields::STATUS_PEKERJAAN,
    ];

    private const CONTROLLED_TAXONOMIES = [
        Taxonomies::KATEGORI_LOWONGAN,
        Taxonomies::LOKASI_PEKERJAAN,
        Taxonomies::JENIS_PEKERJAAN,
        Taxonomies::GENDER,
        Taxonomies::PENDIDIKAN,
    ];

    public function getPermissionErrorStatus($request = null): ?int
    {
        return ControllerUtils::getPermissionErrorStatus($request);
    }

    /**
     * @return true|\WP_Error
     */
    public function permissionsCheck($request = null)
    {
        $status = ControllerUtils::getPermissionErrorStatus($request);
        if ($status === null) {
            return true;
        }

        $code = $status === 401 ? 'wplokerbjm_rest_unauthorized' : 'wplokerbjm_rest_forbidden';
        $message = $status === 401
            ? 'Authentication required.'
            : 'You do not have permission to ingest lowongan drafts.';

        Logger::warning(self::LOG_CATEGORY, 'Ingest permission check failed.', [
            'status' => $status,
            'code' => $code,
        ]);

        return new \WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function ingest(\WP_REST_Request $request)
    {
        $payloadJson = $request->get_param('payload');
        $files = $request->get_file_params();

        $payload = json_decode((string) $payloadJson, true);
        if (!is_array($payload)) {
            Logger::warning(self::LOG_CATEGORY, 'Rejected ingest request with invalid JSON payload.', [
                'json_error' => json_last_error_msg(),
                'has_featured_image' => isset($files['featured_image']),
            ]);

            return new \WP_REST_Response([
                'code' => 'invalid_payload',
                'message' => 'payload must be a valid JSON object.',
                'warnings' => [],
            ], 400);
        }

        $result = $this->createDraftFromPayload($payload, $files['featured_image'] ?? null);

        return new \WP_REST_Response($result['data'], $result['status']);
    }

    /**
     * @param array $payload
     * @param array{tmp_name?: string, name?: string, type?: string, size?: int, error?: int}|null $featuredImage
     * @return IngestResult
     */
    public function createDraftFromPayload(array $payload, ?array $featuredImage): array
    {
        $warnings = [];
        $title = sanitize_text_field((string) ($payload['title'] ?? ''));
        $logContext = $this->buildLogContext($payload, $featuredImage, $title);

        Logger::debug(self::LOG_CATEGORY, 'Starting lowongan draft ingest.', $logContext);

        if ($title === '') {
            Logger::warning(self::LOG_CATEGORY, 'Rejected ingest payload: title is missing.', $logContext);
            return ControllerUtils::errorResult(400, 'missing_title', 'title is required.', $warnings);
        }

        if (!$this->hasMeaningfulDetail($payload)) {
            Logger::warning(self::LOG_CATEGORY, 'Rejected ingest payload: meaningful detail is missing.', $logContext);
            return ControllerUtils::errorResult(400, 'missing_meaningful_detail', 'At least one meaningful detail or contact field is required.', $warnings);
        }

        $imageValidation = $this->validateFeaturedImage($featuredImage);
        if ($imageValidation !== null) {
            Logger::warning(self::LOG_CATEGORY, 'Rejected ingest payload: featured image validation failed.', array_merge(
                $logContext,
                ['code' => $imageValidation['code']]
            ));
            return ControllerUtils::errorResult(400, $imageValidation['code'], $imageValidation['message'], $warnings);
        }

        $hash = hash_file('sha256', (string) $featuredImage['tmp_name']);
        if (!is_string($hash) || $hash === '') {
            Logger::error(self::LOG_CATEGORY, 'Unable to calculate featured image hash.', $logContext);
            return ControllerUtils::errorResult(500, 'featured_image_hash_failed', 'Unable to process featured image.', $warnings);
        }

        $logContext['image_hash_prefix'] = substr($hash, 0, 12);
        $duplicateId = $this->findDuplicatePostId($hash);
        if ($duplicateId !== null) {
            Logger::warning(self::LOG_CATEGORY, 'Duplicate flyer ingest blocked.', array_merge(
                $logContext,
                ['existing_id' => $duplicateId]
            ));

            return [
                'status' => 409,
                'data' => [
                    'code' => 'duplicate_flyer',
                    'message' => 'This flyer was already ingested.',
                    'existing_id' => $duplicateId,
                    'warnings' => $warnings,
                ],
            ];
        }

        $postId = wp_insert_post([
            'post_type' => PostTypes::POST_TYPE_LOWONGAN,
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => '',
        ]);

        if (is_wp_error($postId) || (int) $postId <= 0) {
            Logger::error(self::LOG_CATEGORY, 'WordPress failed to create the lowongan draft.', array_merge(
                $logContext,
                $this->getWordPressErrorContext($postId)
            ));
            return ControllerUtils::errorResult(500, 'post_create_failed', 'Unable to create lowongan draft.', $warnings);
        }

        $postId = (int) $postId;
        $logContext['post_id'] = $postId;

        $metaFields = $this->prepareMetaFields($payload, $warnings);
        foreach ($metaFields as $key => $value) {
            update_post_meta($postId, $key, $value);
        }

        update_post_meta($postId, self::HASH_META_KEY, $hash);
        update_post_meta($postId, self::SOURCE_META_KEY, sanitize_text_field((string) ($payload['source'] ?? $featuredImage['name'])));

        $this->assignTaxonomies($postId, $payload, $warnings);

        $attachmentId = $this->uploadFeaturedImage($featuredImage, $postId);
        if (is_wp_error($attachmentId) || (int) $attachmentId <= 0) {
            Logger::error(self::LOG_CATEGORY, 'Featured image upload failed.', array_merge(
                $logContext,
                $this->getWordPressErrorContext($attachmentId)
            ));
            return ControllerUtils::errorResult(500, 'featured_image_upload_failed', 'Unable to upload featured image.', $warnings);
        }

        $attachmentId = (int) $attachmentId;
        $thumbnailSet = set_post_thumbnail($postId, $attachmentId);
        if ($thumbnailSet === false) {
            Logger::warning(self::LOG_CATEGORY, 'Featured image uploaded but could not be assigned as post thumbnail.', array_merge(
                $logContext,
                ['attachment_id' => $attachmentId]
            ));
        }

        if ($warnings !== []) {
            Logger::warning(self::LOG_CATEGORY, 'Lowongan draft ingest completed with warnings.', array_merge(
                $logContext,
                ['warnings' => $warnings]
            ));
        }

        Logger::info(self::LOG_CATEGORY, 'Lowongan draft ingest completed.', array_merge(
            $logContext,
            [
                'attachment_id' => $attachmentId,
                'meta_fields' => array_keys($metaFields),
                'warning_count' => count($warnings),
            ]
        ));

        return [
            'status' => 201,
            'data' => [
                'id' => $postId,
                'status' => 'draft',
                'edit_url' => (string) get_edit_post_link($postId, ''),
                'permalink' => (string) get_permalink($postId),
                'warnings' => $warnings,
            ],
        ];
    }

    private function hasMeaningfulDetail(array $payload): bool
    {
        $meaningfulFields = array_merge(
            self::WYSIWYG_FIELDS,
            self::CONTACT_FIELDS,
            [
                CustomFields::SOCIAL_MEDIA,
                CustomFields::DEADLINE,
                CustomFields::UMUR_MIN,
                CustomFields::UMUR_MAX,
                CustomFields::PENGALAMAN,
                CustomFields::GAJI_MINIMAL,
                CustomFields::GAJI_MAKSIMAL,
            ]
        );

        foreach ($meaningfulFields as $field) {
            if (isset($payload[$field]) && ControllerUtils::hasNonEmptyValue($payload[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{tmp_name?: string, name?: string, error?: int}|null $featuredImage
     * @return array{code: string, message: string}|null
     */
    private function validateFeaturedImage(?array $featuredImage): ?array
    {
        if ($featuredImage === null) {
            return [
                'code' => 'missing_featured_image',
                'message' => 'featured_image is required.',
            ];
        }

        if (($featuredImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [
                'code' => 'invalid_featured_image',
                'message' => 'featured_image upload failed.',
            ];
        }

        if (empty($featuredImage['tmp_name']) || !is_file((string) $featuredImage['tmp_name'])) {
            return [
                'code' => 'invalid_featured_image',
                'message' => 'featured_image temporary file is missing.',
            ];
        }

        return null;
    }

    private function findDuplicatePostId(string $hash): ?int
    {
        $posts = get_posts([
            'post_type' => PostTypes::POST_TYPE_LOWONGAN,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => self::HASH_META_KEY,
                    'value' => $hash,
                    'compare' => '=',
                ],
            ],
        ]);

        if (is_wp_error($posts) || empty($posts)) {
            if (is_wp_error($posts)) {
                Logger::error(self::LOG_CATEGORY, 'Duplicate flyer lookup failed.', $this->getWordPressErrorContext($posts));
            }
            return null;
        }

        return (int) $posts[0];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> &$warnings
     * @return array<string, mixed>
     */
    private function prepareMetaFields(array $payload, array &$warnings): array
    {
        $meta = [];

        foreach (self::TEXT_FIELDS as $field) {
            if (isset($payload[$field]) && ControllerUtils::hasNonEmptyValue($payload[$field])) {
                $meta[$field] = sanitize_text_field((string) $payload[$field]);
            }
        }

        foreach (self::WYSIWYG_FIELDS as $field) {
            if (isset($payload[$field]) && ControllerUtils::hasNonEmptyValue($payload[$field])) {
                $meta[$field] = wp_kses_post((string) $payload[$field]);
            }
        }

        foreach (self::CONTACT_FIELDS as $field) {
            if (isset($payload[$field]) && ControllerUtils::hasNonEmptyValue($payload[$field])) {
                $meta[$field] = ControllerUtils::sanitizeContactList($field, $payload[$field]);
            }
        }

        if (isset($payload[CustomFields::SOCIAL_MEDIA]) && !empty($payload[CustomFields::SOCIAL_MEDIA])) {
            $socialMedia = ControllerUtils::sanitizeSocialMediaFieldset($payload[CustomFields::SOCIAL_MEDIA]);
            if ($socialMedia !== []) {
                $meta[CustomFields::SOCIAL_MEDIA] = $socialMedia;
            }
        }

        foreach (self::INT_FIELDS as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
                continue;
            }

            if (!is_int($payload[$field]) && !(is_string($payload[$field]) && preg_match('/^-?\d+$/', $payload[$field]))) {
                $warnings[] = "Invalid integer field skipped: {$field}";
                continue;
            }

            $value = (int) $payload[$field];
            if (
                $field === CustomFields::STATUS_PEKERJAAN && !in_array($value, [
                    CustomFields::STATUS_PEKERJAAN_NORMAL,
                    CustomFields::STATUS_PEKERJAAN_URGENT,
                    CustomFields::STATUS_PEKERJAAN_PINNED,
                ], true)
            ) {
                $warnings[] = 'Invalid status_pekerjaan skipped.';
                continue;
            }

            $meta[$field] = $value;
        }

        if (isset($payload[CustomFields::DEADLINE]) && trim((string) $payload[CustomFields::DEADLINE]) !== '') {
            $deadline = trim((string) $payload[CustomFields::DEADLINE]);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) === 1) {
                $meta[CustomFields::DEADLINE] = $deadline;
            } else {
                $warnings[] = 'Invalid deadline skipped.';
            }
        }

        return $meta;
    }

    private function assignTaxonomies(int $postId, array $payload, array &$warnings): void
    {
        if (isset($payload[Taxonomies::PERUSAHAAN]) && trim((string) $payload[Taxonomies::PERUSAHAAN]) !== '') {
            $warnings[] = 'perusahaan taxonomy is reserved for manual review and was not assigned.';
        }

        foreach (self::CONTROLLED_TAXONOMIES as $taxonomy) {
            if (!isset($payload[$taxonomy]) || trim((string) $payload[$taxonomy]) === '') {
                continue;
            }

            $termIds = $this->resolveTermIds((string) $taxonomy, (string) $payload[$taxonomy], $warnings);
            if ($termIds === []) {
                continue;
            }

            $result = wp_set_object_terms($postId, $termIds, $taxonomy, false);
            if (is_wp_error($result)) {
                Logger::error(self::LOG_CATEGORY, 'Taxonomy assignment failed.', array_merge(
                    [
                        'post_id' => $postId,
                        'taxonomy' => $taxonomy,
                        'term_ids' => $termIds,
                    ],
                    $this->getWordPressErrorContext($result)
                ));
            }
        }
    }

    /**
     * @param array<int, string> &$warnings
     * @return list<int>
     */
    private function resolveTermIds(string $taxonomy, string $value, array &$warnings): array
    {
        $availableTerms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($availableTerms) || !is_array($availableTerms)) {
            Logger::error(self::LOG_CATEGORY, 'Unable to load controlled taxonomy terms.', array_merge(
                ['taxonomy' => $taxonomy],
                $this->getWordPressErrorContext($availableTerms)
            ));
            return [];
        }

        $index = [];
        foreach ($availableTerms as $term) {
            $index[mb_strtolower((string) $term->name)] = (int) $term->term_id;
            $index[mb_strtolower((string) $term->slug)] = (int) $term->term_id;
        }

        $parts = $this->splitTaxonomyValue((string) $taxonomy, $value);
        $termIds = [];

        foreach ($parts as $part) {
            $key = mb_strtolower($part);
            if (isset($index[$key])) {
                $termIds[] = $index[$key];
                continue;
            }

            $warnings[] = "Unknown " . (string) $taxonomy . " term skipped: {$part}";
        }

        return array_values(array_unique($termIds));
    }

    /**
     * @return list<string>
     */
    private function splitTaxonomyValue(string $taxonomy, string $value): array
    {
        $parts = array_map('trim', explode(',', $value));

        if ($taxonomy === Taxonomies::GENDER) {
            $expanded = [];
            foreach ($parts as $part) {
                foreach (preg_split('/\s*\/\s*/', $part) ?: [] as $genderPart) {
                    if (trim($genderPart) !== '') {
                        $expanded[] = trim($genderPart);
                    }
                }
            }
            $parts = $expanded;
        }

        return array_values(array_filter($parts, fn($part) => $part !== ''));
    }

    /**
     * @param array{tmp_name?: string, name?: string, type?: string, size?: int, error?: int} $featuredImage
     * @return int|\WP_Error
     */
    private function uploadFeaturedImage(array $featuredImage, int $postId)
    {
        if (!function_exists('media_handle_upload') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $_FILES['featured_image'] = $featuredImage;

        return media_handle_upload('featured_image', $postId);
    }

    /**
     * @param array $payload
     * @param array{name?: string, type?: string, size?: int, error?: int}|null $featuredImage
     * @return array{title: string, source: string, payload_fields: list<string>, image_name: string, image_type: string, image_size: int|null, image_upload_error: int|null}
     */
    private function buildLogContext(array $payload, ?array $featuredImage, string $title): array
    {
        return [
            'title' => $title,
            'source' => sanitize_text_field((string) ($payload['source'] ?? '')),
            'payload_fields' => array_values(array_map('strval', array_keys($payload))),
            'image_name' => sanitize_text_field((string) ($featuredImage['name'] ?? '')),
            'image_type' => sanitize_text_field((string) ($featuredImage['type'] ?? '')),
            'image_size' => isset($featuredImage['size']) ? (int) $featuredImage['size'] : null,
            'image_upload_error' => isset($featuredImage['error']) ? (int) $featuredImage['error'] : null,
        ];
    }

    /**
     * @param mixed $value
     * @return array{wp_error_code?: string, wp_error_message?: string}
     */
    private function getWordPressErrorContext($value): array
    {
        if (!is_object($value)) {
            return [];
        }

        $context = [];
        if (method_exists($value, 'get_error_code')) {
            $context['wp_error_code'] = (string) $value->get_error_code();
        }
        if (method_exists($value, 'get_error_message')) {
            $context['wp_error_message'] = (string) $value->get_error_message();
        }

        return $context;
    }

}

class LowonganIngestOptionsController
{
    private const SCHEMA = 'lowongan_ingest_options.v1';

    /**
     * Taxonomies the agent may choose from during automated ingest.
     *
     * The perusahaan taxonomy is intentionally excluded because it is reserved
     * for human curation.
     *
     * @var string[]
     */
    private const AGENT_TAXONOMIES = [
        Taxonomies::KATEGORI_LOWONGAN,
        Taxonomies::LOKASI_PEKERJAAN,
        Taxonomies::JENIS_PEKERJAAN,
        Taxonomies::GENDER,
        Taxonomies::PENDIDIKAN,
    ];

    /**
     * Return an HTTP status code for permission failures, or null when allowed.
     */
    public function getPermissionErrorStatus($request = null): ?int
    {
        return ControllerUtils::getPermissionErrorStatus($request);
    }

    /**
     * Permission callback for the REST route.
     *
     * @return true|\WP_Error
     */
    public function permissionsCheck($request = null)
    {
        $status = ControllerUtils::getPermissionErrorStatus($request);
        if ($status === null) {
            return true;
        }

        $code = $status === 401 ? 'wplokerbjm_rest_unauthorized' : 'wplokerbjm_rest_forbidden';
        $message = $status === 401
            ? 'Authentication required.'
            : 'You do not have permission to access ingest options.';

        return new \WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function options()
    {
        return new \WP_REST_Response($this->getOptionsData(), 200);
    }

    /**
     * @return array{schema: string, taxonomies: array<string, list<array{id: int, name: string, slug: string, parent: int}>>, reserved_taxonomies: string[], status_pekerjaan: list<array{value: int, label: string}>}
     */
    public function getOptionsData(): array
    {
        return [
            'schema' => self::SCHEMA,
            'taxonomies' => TaxonomyQuery::getTaxonomyOptions(self::AGENT_TAXONOMIES),
            'reserved_taxonomies' => [
                Taxonomies::PERUSAHAAN,
            ],
            CustomFields::STATUS_PEKERJAAN => [
                [
                    'value' => CustomFields::STATUS_PEKERJAAN_NORMAL,
                    'label' => 'Normal',
                ],
                [
                    'value' => CustomFields::STATUS_PEKERJAAN_URGENT,
                    'label' => 'Urgent',
                ],
                [
                    'value' => CustomFields::STATUS_PEKERJAAN_PINNED,
                    'label' => 'Pinned',
                ],
            ],
        ];
    }
}
