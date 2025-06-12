<?php

namespace AstraChild\Controllers\REST;

class DropdownOptions
{
    public static function handle(\WP_REST_Request $request)
    {
        $taxonomy = $request->get_param('taxonomy');
        $search = $request->get_param('search') ?? '';
        $page = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(50, max(10, intval($request->get_param('per_page') ?? 20))); // Limit between 10-50
        $parent = intval($request->get_param('parent') ?? 0);
        
        if (!$taxonomy || !taxonomy_exists($taxonomy)) {
            return new \WP_Error('invalid_taxonomy', 'Invalid taxonomy', ['status' => 400]);
        }

        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ];

        // Add search functionality
        if (!empty($search)) {
            $args['name__like'] = $search;
            // When searching, remove parent restriction to get all matching terms
            unset($args['parent']);
        } else {
            // Only show top-level terms initially, unless parent is specified
            $args['parent'] = $parent;
        }

        $terms = get_terms($args);

        if (is_wp_error($terms)) {
            return new \WP_Error('fetch_error', 'Failed to fetch terms', ['status' => 500]);
        }

        // Get total count for pagination
        $count_args = $args;
        unset($count_args['number'], $count_args['offset']);
        $count_args['fields'] = 'count';
        $total_terms = get_terms($count_args);
        
        if (is_wp_error($total_terms)) {
            $total_terms = 0;
        }

        $options = [];
        foreach ($terms as $term) {
            $children_query = [
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
                'parent' => $term->term_id,
                'fields' => 'all'
            ];
            
            $children = get_terms($children_query);
            $has_children_count = is_array($children) ? count($children) : 0;

            $options[] = [
                'id' => $term->slug,
                'text' => $term->name,
                'parent' => $term->parent,
                'term_id' => $term->term_id,
                'count' => $term->count,
                'has_children' => $has_children_count > 0,
                'depth' => self::getTermDepth($term->term_id, $taxonomy)
            ];
        }

        return rest_ensure_response([
            'options' => $options,
            'pagination' => [
                'more' => ($page * $per_page) < $total_terms,
                'current_page' => $page,
                'per_page' => $per_page,
                'total' => $total_terms
            ]
        ]);
    }

    /**
     * Calculate term depth in hierarchy
     */
    private static function getTermDepth($term_id, $taxonomy, $depth = 0)
    {
        $term = get_term($term_id, $taxonomy);
        
        if (is_wp_error($term) || !$term || $term->parent == 0) {
            return $depth;
        }
        
        return self::getTermDepth($term->parent, $taxonomy, $depth + 1);
    }
}