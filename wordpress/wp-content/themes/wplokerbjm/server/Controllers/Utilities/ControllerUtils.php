<?php
namespace WPLokerBJM\Controllers\Utilities;
use SearchFilters;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\Sanitizer;
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
            if (is_array($param)) {
                return $param;
            }
            if (is_string($param) && $param !== '') {
                return Sanitizer::splitAndClean(',', $param);
            }
            return [];
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
     * @return list<positive-int> Positive integer IDs only
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

}
