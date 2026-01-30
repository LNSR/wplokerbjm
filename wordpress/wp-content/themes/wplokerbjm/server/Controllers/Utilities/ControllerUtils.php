<?php
namespace WPLokerBJM\Controllers\Utilities;
use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Log\Logger;
class ControllerUtils
{

    public static function parseJobFilters(\WP_REST_Request $request): array
    {
        $parseMulti = function ($param) {
            if (is_array($param))
                return $param;
            if (is_string($param) && strpos($param, ',') !== false) {
                return array_filter(array_map('trim', explode(',', $param)));
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

    public static function failedResponse(string $message, int $code = 400): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'error' => $message,
        ], $code);
    }

    public static function buildTermsTree(array $terms, $taxonomy = ''): array
    {
        try {

            $terms_by_id = [];
            foreach ($terms as $term) {
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
     * Validate and filter an array of IDs
     *
     * @param array $ids Array of IDs to validate
     * @return array Filtered array of valid positive integer IDs
     */
    public static function validateIds(array $ids): array
    {
        return array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });
    }
}