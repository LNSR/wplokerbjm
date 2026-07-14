<?php
namespace WPLokerBJM\Controllers\Utilities;
use SearchFilters;
use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\QueryBuilders\JobQuery;

/**
 * @phpstan-import-type SearchFilters from JobQuery
 */
class ControllerUtils
{
    /**
     * @param \WP_REST_Request $request
     * @return SearchFilters
     */
    public static function parseJobFilters($request): array
    {
        $parseMulti = static function ($param) {
            if (is_array($param))
                return $param;
            if (is_string($param) && str_contains($param, ',')) { // Optimized from strpos
                return $param
                    |> (static fn($str) => explode(',', $str))
                    |> (static fn($arr) => array_map('trim', $arr))
                    |> array_filter(...);
            }
            return $param ? [$param] : [];
        };

        return [
            'cari' => $request->get_param('cari') ?? '',
            Taxonomies::LOKASI_PEKERJAAN => $parseMulti($request->get_param(Taxonomies::LOKASI_PEKERJAAN)),
            Taxonomies::GENDER => $parseMulti($request->get_param(Taxonomies::GENDER)),
            Taxonomies::PENDIDIKAN => $parseMulti($request->get_param(Taxonomies::PENDIDIKAN)),
            'sort' => $request->get_param('sort') ?? 'desc',
        ];
    }

    /**
     * @param string $message Error message
     * @param int $code HTTP status code (default 400)
     * @return \WP_REST_Response
     */
    public static function failedResponse($message, $code = 400)
    {
        return new \WP_REST_Response([
            'success' => false,
            'error' => $message,
        ], $code);
    }

    /**
     * Build a hierarchical tree from WP_Term objects.
     *
     * @template T of \WP_Term
     * @param T[] $terms
     * @return list<array{slug: string, name: string, parent: int, children: list<array{slug: string, name: string, parent: int, children: list<mixed>}>}>
     */
    public static function buildTermsTree(array $terms, $taxonomy = ''): array
    {
        try {
            $terms_by_id = [];
            foreach ($terms as &$term) {
                $terms_by_id[$term->term_id] = [
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'parent' => $term->parent,
                    'children' => [],
                ];
            }
            $tree = [];
            foreach ($terms_by_id as &$term) {
                if ($term['parent'] && isset($terms_by_id[$term['parent']])) {
                    $terms_by_id[$term['parent']]['children'][] = &$term;
                } else {
                    $tree[] = &$term;
                }
            }
            unset($term);

            return $tree;
        } catch (\Exception $e) {
            Logger::error('Taxonomy', 'TaxonomyService::buildTermsTree error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate and filter an array of IDs.
     *
     * @param array<int, mixed> $ids
     * @return list<int> Positive integer IDs only
     */
    public static function validateIds(array $ids): array
    {
        return $ids
            |> (static fn($arr) => array_map('intval', $arr))
            |> (static fn($arr) => array_filter($arr, static fn($id) => $id > 0));
    }

    /**
     * @param \WP_REST_Request|mixed $request
     * @return bool
     */
    public static function hasBearerAuthorization($request): bool
    {
        $authorization = '';

        if (is_object($request) && method_exists($request, 'get_header')) {
            $authorization = (string) $request->get_header('authorization');
        }

        if ($authorization === '') {
            $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        return $authorization
            |> (static fn($auth) => trim((string) $auth))
            |> (static fn($auth) => preg_match('/^Bearer\s+\S+$/i', $auth) === 1);
    }

    /**
     * @param \WP_REST_Request|mixed $request
     * @return int|null 401|403|null
     */
    public static function getPermissionErrorStatus($request = null): ?int
    {
        if (!self::hasBearerAuthorization($request)) {
            return 401;
        }

        if (!is_user_logged_in()) {
            return 401;
        }

        if (!current_user_can('edit_posts')) {
            return 403;
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public static function hasNonEmptyValue($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::hasNonEmptyValue($item)) {
                    return true;
                }
            }
            return false;
        }

        return trim((string) $value) !== '';
    }

    /**
     * Sanitize contact list values (email, URL, text).
     *
     * @param string $field The contact field key
     * @param string|string[] $value Raw contact value(s)
     * @return list<non-empty-string> Sanitized contact entries
     */
    public static function sanitizeContactList(string $field, $value): array
    {
        $rawParts = is_array($value) ? $value : explode(',', (string) $value);

        $parts = $rawParts
            |> (static fn($arr) => array_map(static fn($part) => trim((string) $part), $arr))
            |> (static fn($arr) => array_filter($arr, static fn($part) => $part !== ''))
            |> array_values(...);

        return $parts
            |> (static fn($arr) => array_map(static fn($part) => match ($field) {
                CustomFields::EMAIL_KONTAK => sanitize_email($part),
                CustomFields::SITUS_KONTAK => esc_url_raw($part),
                default => sanitize_text_field($part),
            }, $arr))
            |> (static fn($arr) => array_filter($arr, static fn($part) => $part !== null && $part !== ''))
            |> array_values(...);
    }

    /**
     * Sanitize social media fieldset data from Meta Box.
     *
     * @param string|array<int, array<string, string>>|array<string, string> $value Raw social media data
     * @return list<array<string, non-empty-string>> Sanitized social media sets
     */
    public static function sanitizeSocialMediaFieldset($value): array
    {
        $allowedIndex = CustomFields::SOCIAL_MEDIA_PLATFORMS;

        if (is_string($value)) {
            $value = self::parseSocialMediaString($value);
        }

        if (!is_array($value)) {
            return [];
        }

        $sets = self::isAssoc($value) ? [$value] : $value;
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
    private static function parseSocialMediaString(string $value): array
    {
        $set = [];

        $items = $value
            |> (static fn($str) => explode(';', $str))
            |> (static fn($arr) => array_map('trim', $arr))
            |> array_filter(...);

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

    /**
     * @param array $value
     * @return bool
     */
    private static function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * @return array{status: int, data: array{code: string, message: string, warnings: array}}
     */
    public static function errorResult(int $status, string $code, string $message, array $warnings): array
    {
        return [
            'status' => $status,
            'data' => [
                'code' => $code,
                'message' => $message,
                'warnings' => $warnings,
            ],
        ];
    }
}