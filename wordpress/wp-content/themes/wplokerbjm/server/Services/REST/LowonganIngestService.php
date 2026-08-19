<?php

declare(strict_types=1);

namespace WPLokerBJM\Services\REST;

use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\QueryBuilders\{JobQuery, TaxonomyQuery};
use WPLokerBJM\Repositories\TaxonomyRepository;
use WPLokerBJM\Services\Utilities\{ServiceUtils, ServiceIngestUtils};
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\Sanitizer;
use DI\Attribute\Injectable;

/**
 * @phpstan-type IngestErrorResult array{status: 400|500, data: array{code: string, message: string, warnings: array}}
 * @phpstan-type IngestDuplicateResult array{status: 409, data: array{code: string, message: string, existing_id: int, warnings: array}}
 * @phpstan-type IngestSuccessResult array{status: 201, data: array{id: int, status: string, edit_url: string, permalink: string, warnings: array}}
 * @phpstan-type IngestResult IngestErrorResult|IngestDuplicateResult|IngestSuccessResult
 * @template ErrorExtra of array
 */
#[Injectable(lazy: true)]
class LowonganIngestService
{
    private const SOURCE_META_KEY = '_wplokerbjm_ingest_source';

    public function __construct(
        private readonly LowonganIngestImageHandler $imageHandler,
        private readonly LowonganIngestPayloadHandler $payloadHandler,
        private readonly LowonganIngestTaxonomyResolver $taxonomyResolver,
        private readonly LowonganIngestLogBuilder $logBuilder,
    ) {}

    /**
     * Orchestrates the lowongan draft ingestion pipeline.
     *
     * @param array $payload
     * @param array{tmp_name?: string, name?: string, type?: string, size?: int, error?: int}|null $featuredImage
     * @return IngestResult
     */
    public function createDraftFromPayload(array $payload, ?array $featuredImage): array
    {
        $warnings = [];
        $title = sanitize_text_field((string) ($payload['title'] ?? ''));
        $logContext = $this->logBuilder->buildLogContext($payload, $featuredImage, $title);

        Logger::debug(LowonganIngestLogBuilder::LOG_CATEGORY, 'Starting lowongan draft ingest.', $logContext);

        if ($title === '') {
            Logger::warning(LowonganIngestLogBuilder::LOG_CATEGORY, 'Rejected ingest payload: title is missing.', $logContext);
            return $this->errorResult(400, 'missing_title', 'title is required.', $warnings);
        }

        if (!$this->payloadHandler->hasMeaningfulDetail($payload)) {
            Logger::warning(LowonganIngestLogBuilder::LOG_CATEGORY, 'Rejected ingest payload: meaningful detail is missing.', $logContext);
            return $this->errorResult(400, 'missing_meaningful_detail', 'At least one meaningful detail or contact field is required.', $warnings);
        }

        $imageError = $this->imageHandler->validateFeaturedImage($featuredImage);
        if ($imageError !== null) {
            Logger::warning(LowonganIngestLogBuilder::LOG_CATEGORY, 'Rejected ingest payload: featured image validation failed.', array_merge($logContext, ['code' => $imageError['code']]));
            return $this->errorResult(400, $imageError['code'], $imageError['message'], $warnings);
        }

        /** @var array{tmp_name: string, name?: string} $featuredImage */
        $imageHash = $this->imageHandler->generateImageHash($featuredImage['tmp_name']);
        if ($imageHash === null) {
            Logger::error(LowonganIngestLogBuilder::LOG_CATEGORY, 'Unable to calculate featured image hash.', $logContext);
            return $this->errorResult(500, 'featured_image_hash_failed', 'Unable to process featured image.', $warnings);
        }

        $logContext['image_hash_prefix'] = substr($imageHash, 0, 12);
        $duplicateId = $this->imageHandler->findDuplicatePostId($imageHash);
        if ($duplicateId !== null) {
            Logger::warning(LowonganIngestLogBuilder::LOG_CATEGORY, 'Duplicate flyer ingest blocked.', array_merge($logContext, ['existing_id' => $duplicateId]));
            return $this->errorResult(409, 'duplicate_flyer', 'This flyer was already ingested.', $warnings, ['existing_id' => $duplicateId]);
        }

        $postId = wp_insert_post([
            'post_type' => PostTypes::POST_TYPE_LOWONGAN,
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => '',
        ]);

        if (is_wp_error($postId) || (int) $postId <= 0) {
            Logger::error(LowonganIngestLogBuilder::LOG_CATEGORY, 'WordPress failed to create the lowongan draft.', array_merge($logContext, $this->logBuilder->getWordPressErrorContext($postId)));
            return $this->errorResult(500, 'post_create_failed', 'Unable to create lowongan draft.', $warnings);
        }

        $postId = (int) $postId;
        $logContext['post_id'] = $postId;

        $metaFields = $this->payloadHandler->prepareMetaFields($payload, $warnings);
        foreach ($metaFields as $key => $value) {
            update_post_meta($postId, $key, $value);
        }

        update_post_meta($postId, LowonganIngestImageHandler::HASH_META_KEY, $imageHash);
        update_post_meta($postId, self::SOURCE_META_KEY, sanitize_text_field((string) ($payload['source'] ?? $featuredImage['name'])));

        $this->taxonomyResolver->assignTaxonomies($postId, $payload, $warnings);

        $attachmentId = $this->imageHandler->uploadAndAttachFeaturedImage($featuredImage, $postId, $logContext);
        if ($attachmentId === null) {
            return $this->errorResult(500, 'featured_image_upload_failed', 'Unable to upload featured image.', $warnings);
        }

        if ($warnings !== []) {
            Logger::warning(LowonganIngestLogBuilder::LOG_CATEGORY, 'Lowongan draft ingest completed with warnings.', array_merge($logContext, ['warnings' => $warnings]));
        }

        Logger::info(LowonganIngestLogBuilder::LOG_CATEGORY, 'Lowongan draft ingest completed.', array_merge($logContext, [
            'attachment_id' => $attachmentId,
            'meta_fields' => array_keys($metaFields),
            'warning_count' => count($warnings),
        ]));

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

    /**
     * @param int $status
     * @param string $code
     * @param string $message
     * @param array $warnings
     * @param ErrorExtra $extra
     * @return array{status: int, data: array{code: string, message: string, warnings: array, ErrorExtra}}
     */
    private function errorResult(int $status, string $code, string $message, array $warnings, array $extra = []): array
    {
        return [
            'status' => $status,
            'data' => [
                'code' => $code,
                'message' => $message,
                'warnings' => $warnings,
                ...$extra,
            ],
        ];
    }
}

/**
 * Image upload, validation, hash generation, and duplicate detection.
 */
class LowonganIngestImageHandler
{
    public const HASH_META_KEY = '_wplokerbjm_ingest_hash';

    public function __construct(
        private readonly LowonganIngestLogBuilder $logBuilder,
    ) {}

    /**
     * @param array{tmp_name?: string, name?: string, error?: int}|null $featuredImage
     * @return array{code: string, message: string}|null Validation error, or null if valid
     */
    public function validateFeaturedImage(?array $featuredImage): ?array
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

    public function generateImageHash(string $filePath): ?string
    {
        $hash = hash_file('sha256', $filePath);
        return (is_string($hash) && $hash !== '') ? $hash : null;
    }

    public function findDuplicatePostId(string $hash): ?int
    {
        $posts = JobQuery::findPostIdForIngest(self::HASH_META_KEY, $hash);
        if (is_wp_error($posts) || empty($posts)) {
            if (is_wp_error($posts)) {
                Logger::error(LowonganIngestLogBuilder::LOG_CATEGORY, 'Duplicate flyer lookup failed.', $this->logBuilder->getWordPressErrorContext($posts));
            }
            return null;
        }

        return (int) $posts[0];
    }

    /**
     * Uploads the featured image and sets it as the post thumbnail.
     *
     * @param array{tmp_name?: string, name?: string, type?: string, size?: int, error?: int} $featuredImage
     * @param array<string, mixed> $logContext
     */
    public function uploadAndAttachFeaturedImage(array $featuredImage, int $postId, array $logContext): ?int
    {
        if (!function_exists('media_handle_upload') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $_FILES['featured_image'] = $featuredImage;

        $attachmentId = media_handle_upload('featured_image', $postId);
        if (is_wp_error($attachmentId) || (int) $attachmentId <= 0) {
            Logger::error(LowonganIngestLogBuilder::LOG_CATEGORY, 'Featured image upload failed.', array_merge(
                $logContext,
                $this->logBuilder->getWordPressErrorContext($attachmentId),
            ));
            return null;
        }

        $attachmentId = (int) $attachmentId;
        $thumbnailSet = set_post_thumbnail($postId, $attachmentId);
        if ($thumbnailSet === false) {
            Logger::warning(LowonganIngestLogBuilder::LOG_CATEGORY, 'Featured image uploaded but could not be assigned as post thumbnail.', array_merge(
                $logContext,
                ['attachment_id' => $attachmentId],
            ));
        }

        return $attachmentId;
    }
}

/**
 * Payload validation and meta-field mapping.
 */
class LowonganIngestPayloadHandler
{
    /**
     * @param array<string, mixed> $payload
     */
    public function hasMeaningfulDetail(array $payload): bool
    {
        $meaningfulFields = array_merge(
            CustomFields::WYSIWYG_FIELDS,
            CustomFields::CONTACT_FIELDS,
            [
                CustomFields::SOCIAL_MEDIA,
                CustomFields::DEADLINE,
                CustomFields::UMUR_MIN,
                CustomFields::UMUR_MAX,
                CustomFields::PENGALAMAN,
                CustomFields::GAJI_MINIMAL,
                CustomFields::GAJI_MAKSIMAL,
            ],
        );

        foreach ($meaningfulFields as $field) {
            if (isset($payload[$field]) && ServiceUtils::hasNonEmptyValue($payload[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Maps raw payload fields to sanitized post meta data.
     *
     * @param array<string, mixed> $payload
     * @param array<int, string> &$warnings
     * @return array<string, mixed>
     */
    public function prepareMetaFields(array $payload, array &$warnings): array
    {
        $meta = [];

        foreach (CustomFields::TEXT_FIELDS as $field) {
            if (isset($payload[$field]) && ServiceUtils::hasNonEmptyValue($payload[$field])) {
                $meta[$field] = sanitize_text_field((string) $payload[$field]);
            }
        }

        foreach (CustomFields::WYSIWYG_FIELDS as $field) {
            if (isset($payload[$field]) && ServiceUtils::hasNonEmptyValue($payload[$field])) {
                $meta[$field] = Sanitizer::wysiwyg((string) $payload[$field]);
            }
        }

        foreach (CustomFields::CONTACT_FIELDS as $field) {
            if (isset($payload[$field]) && ServiceUtils::hasNonEmptyValue($payload[$field])) {
                $meta[$field] = Sanitizer::contactFieldList($field, $payload[$field]);
            }
        }

        if (isset($payload[CustomFields::SOCIAL_MEDIA]) && !empty($payload[CustomFields::SOCIAL_MEDIA])) {
            $socialMedia = $this->sanitizeSocialMediaFieldset($payload[CustomFields::SOCIAL_MEDIA]);
            if ($socialMedia !== []) {
                $meta[CustomFields::SOCIAL_MEDIA] = $socialMedia;
            }
        }

        foreach (CustomFields::INT_FIELDS as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
                continue;
            }

            $value = Sanitizer::intOrNull($payload[$field]);
            if ($value === null) {
                $warnings[] = "Invalid integer field skipped: {$field}";
                continue;
            }

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

        if (isset($payload[CustomFields::DEADLINE])) {
            $deadline = Sanitizer::deadline((string) $payload[CustomFields::DEADLINE]);
            if ($deadline !== null) {
                $meta[CustomFields::DEADLINE] = $deadline;
            } else {
                $warnings[] = 'Invalid deadline skipped.';
            }
        }

        return $meta;
    }
    /**
     * Sanitize social media fieldset data from Meta Box.
     *
     * @param string|array<int, array<string, string>>|array<string, string> $value Raw social media data
     * @return list<array<string, non-empty-string>> Sanitized social media sets
     */
    private function sanitizeSocialMediaFieldset($value): array
    {
        $allowedIndex = CustomFields::SOCIAL_MEDIA_PLATFORMS;

        if (is_string($value)) {
            $value = $this->parseSocialMediaString($value);
        }

        if (!is_array($value)) {
            return [];
        }

        $sets = !\array_is_list($value) ? [$value] : $value;
        $sanitizedSets = [];

        foreach ($sets as $set) {
            if (!is_array($set)) {
                continue;
            }

            $sanitizedSet = [];
            foreach ($set as $platform => $username) {
                $platform = sanitize_text_field((string) $platform);
                if (!isset($allowedIndex[$platform])) {
                    continue;
                }

                $username = sanitize_text_field((string) $username);
                if ($username === '') {
                    continue;
                }

                $sanitizedSet[$platform] = $username;
            }

            if ($sanitizedSet !== []) {
                $sanitizedSets[] = $sanitizedSet;
            }
        }

        return $sanitizedSets;
    }

    /**
     * Parse a social media string format "platform:username;platform:username" into an array set.
     *
     * @param string $value Semicolon-separated platform:username pairs
     * @return list<array<string, string>> Single-element list containing the parsed set, or empty list
     */
    private function parseSocialMediaString(string $value): array
    {
        $set = [];

        $items = Sanitizer::splitAndClean(';', $value);

        foreach ($items as $item) {
            $parts = explode(':', $item, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $platform = trim($parts[0]);
            $username = trim($parts[1]);
            if ($platform !== '' && $username !== '') {
                $set[$platform] = $username;
            }
        }

        return $set === [] ? [] : [$set];
    }
}

/**
 * Taxonomy matching and assignment for agent-controlled taxonomies.
 */
class LowonganIngestTaxonomyResolver
{
    private const CONTROLLED_TAXONOMIES = [
        Taxonomies::KATEGORI_LOWONGAN,
        Taxonomies::LOKASI_PEKERJAAN,
        Taxonomies::JENIS_PEKERJAAN,
        Taxonomies::GENDER,
        Taxonomies::PENDIDIKAN,
    ];

    public function __construct(
        private readonly LowonganIngestLogBuilder $logBuilder,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> &$warnings
     */
    public function assignTaxonomies(int $postId, array $payload, array &$warnings): void
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
                Logger::error(LowonganIngestLogBuilder::LOG_CATEGORY, 'Taxonomy assignment failed.', array_merge(
                    [
                        'post_id' => $postId,
                        'taxonomy' => $taxonomy,
                        'term_ids' => $termIds,
                    ],
                    $this->logBuilder->getWordPressErrorContext($result),
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
        $availableTerms = TaxonomyQuery::allTaxonomiesTerms((string) $taxonomy, 'all');

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

            $warnings[] = "Unknown " . (string) $taxonomy . " term skipped: " . (string) $part;
        }

        return array_values(array_unique($termIds));
    }

    /**
     * @return list<string>
     */
    private function splitTaxonomyValue(string $taxonomy, string $value): array
    {
        $parts = Sanitizer::splitAndClean(',', $value);

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

        return array_values($parts);
    }
}

/**
 * Structured logging context builder for the ingest pipeline.
 */
class LowonganIngestLogBuilder
{
    public const LOG_CATEGORY = 'LowonganIngest';

    /**
     * @param array $payload
     * @param array{name?: string, type?: string, size?: int, error?: int}|null $featuredImage
     * @return array{title: string, source: string, payload_fields: list<string>, image_name: string, image_type: string, image_size: int|null, image_upload_error: int|null}
     */
    public function buildLogContext(array $payload, ?array $featuredImage, string $title): array
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
     * @param ?\WP_Error $value
     * @return array{wp_error_code?: string, wp_error_message?: string}
     */
    public function getWordPressErrorContext(?\WP_Error $value): array
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
