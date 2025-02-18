<?php
namespace AstraChild\Controllers;

use AstraChild\Models\TaxonomyModel;
use AstraChild\Helpers\TaxonomyHelpers;

class TaxonomyController
{
    /**
     * @var TaxonomyModel
     */
    protected $taxonomyModel;

    /**
     * @var TaxonomyHelpers
     */
    protected $taxonomyHelpers;
    
    /**
     * Initialize controller
     * 
     * @param TaxonomyModel|null $taxonomyModel Optional model instance
     */
    public function __construct(?TaxonomyModel $taxonomyModel = null)
    {
        $this->taxonomyModel = $taxonomyModel ?? new TaxonomyModel();
        $this->taxonomyHelpers = new TaxonomyHelpers();
    }
    
    /**
     * Get smart filter data
     * 
     * @param array $context Context data
     * @param bool $only_used Show only used terms
     * @param int $limit Term limit
     * @return array Smart filter data
     */
    
    /**
     * Filter configuration
     * Controls which filters are active and their mapping
     * 
     * @return array Filter configuration
     */
    public function getFilterConfiguration(): array
    {
        return [
            // Format: 'param_name' => ['taxonomy' => 'taxonomy-slug', 'active' => true/false]
            'keywords'   => ['field' => 's', 'active' => true],
            'loc'        => ['taxonomy' => 'lokasi-pekerjaan', 'field' => 'slug', 'active' => true],
            'pengalaman' => ['taxonomy' => 'pengalaman', 'field' => 'slug', 'active' => true],
            'pendidikan' => ['taxonomy' => 'pendidikan', 'field' => 'slug', 'active' => true],
            'jenis'      => ['taxonomy' => 'jenis-pekerjaan', 'field' => 'slug', 'active' => true],
            'gender'     => ['taxonomy' => 'gender', 'field' => 'slug', 'active' => true],
            'gaji'       => ['taxonomy' => 'gaji', 'field' => 'slug', 'active' => true],
            'kategori'   => ['taxonomy' => 'category', 'field' => 'slug', 'active' => true],
            // Add more filters as needed
        ];
    }

        /**
     * Get hierarchical filter options for UI elements
     * 
     * @return array Hierarchical filter options
     */
    public function getHierarchicalFilterOptions(): array
    {
        $hierarchical_taxonomies = [
            'lokasi-pekerjaan' => ['label' => 'Lokasi', 'param' => 'loc'],
            'category' => ['label' => 'Kategori', 'param' => 'kategori']
        ];

        $flat_taxonomies = [
            'jenis-pekerjaan' => ['label' => 'Jenis Pekerjaan', 'param' => 'jenis'],
            'pengalaman' => ['label' => 'Pengalaman', 'param' => 'pengalaman'],
            'pendidikan' => ['label' => 'Pendidikan', 'param' => 'pendidikan'],
            'gender' => ['label' => 'Gender', 'param' => 'gender'],
            'gaji' => ['label' => 'Gaji', 'param' => 'gaji']
        ];

        $filter_options = [];

        // Process hierarchical taxonomies
        foreach ($hierarchical_taxonomies as $taxonomy => $config) {
            $filter_options[$config['param']] = [
                'label' => $config['label'],
                'type' => 'hierarchical',
                'terms' => $this->getHierarchicalTerms($taxonomy)
            ];
        }

        // Process flat taxonomies
        foreach ($flat_taxonomies as $taxonomy => $config) {
            $terms = $this->taxonomyModel->getTerms($taxonomy);

            $flat_terms = [];
            if (!empty($terms)) {
                foreach ($terms as $term) {
                    $flat_terms[$term->slug] = $term->name;
                }
            }

            $filter_options[$config['param']] = [
                'label' => $config['label'],
                'type' => 'flat',
                'terms' => $flat_terms
            ];
        }

        return $filter_options;
    }

    /**
     * Build taxonomy query from parameters
     * 
     * @param array $params Filter parameters
     * @return array Tax query array for WP_Query
     */
    public function buildTaxonomyQueryFromParams(array $params): array
    {
        $tax_query = [];
        $filter_config = $this->getFilterConfiguration();

        foreach ($filter_config as $param => $config) {
            // Skip inactive filters or non-taxonomy filters
            if (!$config['active'] || !isset($config['taxonomy'])) {
                continue;
            }

            // Skip if parameter not set
            if (!isset($params[$param]) || empty($params[$param])) {
                continue;
            }

            $value = sanitize_text_field($params[$param]);

            // Check if this is a hierarchical taxonomy
            $is_hierarchical = is_taxonomy_hierarchical($config['taxonomy']);

            if ($is_hierarchical) {
                $tax_query[] = $this->buildHierarchicalTaxQuery($config['taxonomy'], $value);
            } else {
                $tax_query[] = [
                    'taxonomy' => $config['taxonomy'],
                    'field' => $config['field'] ?? 'slug',
                    'terms' => $value
                ];
            }
        }

        return $tax_query;
    }

    /**
     * Build a hierarchical taxonomy query
     * 
     * @param string $taxonomy Taxonomy name
     * @param string $value Selected term slug
     * @return array WP_Query compatible tax_query array
     */
    public function buildHierarchicalTaxQuery(string $taxonomy, string $value): array
    {
        // First, get the term by slug
        $term = get_term_by('slug', $value, $taxonomy);

        if (!$term) {
            return [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $value
            ];
        }

        // If this is a parent term, get all child terms too
        $child_terms = get_term_children($term->term_id, $taxonomy);

        if (empty($child_terms)) {
            // No children, just filter by this term
            return [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $term->term_id
            ];
        } else {
            // Has children, include them in the filter
            $terms = array_merge([$term->term_id], $child_terms);
            return [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $terms,
                'include_children' => true
            ];
        }
    }

        /**
     * Get hierarchical taxonomy terms
     * 
     * @param string $taxonomy Taxonomy name
     * @param array $args Additional arguments
     * @return array Hierarchical array of terms
     */
    public function getHierarchicalTerms(string $taxonomy, array $args = []): array
    {
        $default_args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC'
        ];

        $args = array_merge($default_args, $args);

        // Get all terms
        $terms = get_terms($args);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        // Group terms by parent
        $term_groups = [];
        foreach ($terms as $term) {
            if (!isset($term_groups[$term->parent])) {
                $term_groups[$term->parent] = [];
            }
            $term_groups[$term->parent][] = $term;
        }

        // Build hierarchical structure recursively
        return $this->buildTermHierarchy(0, $term_groups);
    }

    /**
     * Build hierarchical structure recursively
     * 
     * @param int $parent_id Parent term ID
     * @param array $term_groups Terms grouped by parent ID
     * @return array Hierarchical array of terms
     */
    private function buildTermHierarchy(int $parent_id, array $term_groups): array
    {
        $hierarchy = [];

        // If no children for this parent, return empty array
        if (!isset($term_groups[$parent_id])) {
            return $hierarchy;
        }

        // Add each child term and recursively process its children
        foreach ($term_groups[$parent_id] as $term) {
            $hierarchy[$term->term_id] = [
                'term' => $term,
                'children' => $this->buildTermHierarchy($term->term_id, $term_groups)
            ];
        }

        return $hierarchy;
    }

    /**
     * Get smart filter data that shows only relevant terms
     *
     * @param array $context Current filter selections
     * @param bool $only_used Only show terms that are used in published jobs
     * @param int $limit Limit number of terms per taxonomy (0 for no limit)
     * @return array Smart filtered taxonomy terms
     */
    public function getSmartFilterData(array $context = [], bool $only_used = true, int $limit = 10): array
    {
        $filter_data = [];
        $taxonomies = [
            'lokasi-pekerjaan' => 'locations',
            'jenis-pekerjaan' => 'job_types',
            'gender' => 'genders',
            'pendidikan' => 'education',
            'pengalaman' => 'experiences',
            'gaji' => 'salaries',
            'usia' => 'ages'
        ];

        foreach ($taxonomies as $taxonomy => $key) {
            $args = [
                'taxonomy' => $taxonomy,
                'hide_empty' => $only_used,
                'orderby' => 'count',
                'order' => 'DESC'
            ];

            // Apply contextual filtering if we have context
            if (!empty($context)) {
                $args = $this->applyContextualFiltering($taxonomy, $args, $context);
            }

            // Get terms with arguments
            $terms = $this->taxonomyModel->getTerms($taxonomy, $args);

            // Limit number of terms if needed
            if ($limit > 0 && count($terms) > $limit) {
                $terms = array_slice($terms, 0, $limit);
            }

            $filter_data[$key] = $terms;
        }

        return $filter_data;
    }

    /**
     * Apply contextual filtering to term query args
     *
     * @param string $taxonomy Current taxonomy being queried
     * @param array $args Current query args
     * @param array $context Current filter selections
     * @return array Modified query args
     */
    private function applyContextualFiltering(string $taxonomy, array $args, array $context): array
    {
        // Don't modify args if no contextual filtering needed
        if (empty($context)) {
            return $args;
        }

        // Convert from URL params to taxonomies
        $param_to_tax = array_flip(TaxonomyHelpers::getParamToTaxonomyMapping());

        // Build tax_query for meta_query to filter terms
        $tax_queries = [];
        foreach ($context as $param => $value) {
            // Skip if this is the current taxonomy we're querying
            if (isset($param_to_tax[$param]) && $param_to_tax[$param] == $taxonomy) {
                continue;
            }

            // Skip empty values
            if (empty($value)) {
                continue;
            }

            // Only process known taxonomy parameters
            if (isset($param_to_tax[$param])) {
                $tax_queries[] = [
                    'taxonomy' => $param_to_tax[$param],
                    'field' => 'slug',
                    'terms' => $value
                ];
            }
        }

        // If we have tax queries, add them to args
        if (!empty($tax_queries)) {
            // This requires a custom approach with a subquery, as terms can't be directly filtered by other taxonomies
            // We need to find posts matching the other filters, then get terms from those posts
            $args['include'] = $this->getTermsFromFilteredPosts($taxonomy, $tax_queries);
        }

        return $args;
    }

    /**
     * Get term IDs from posts matching filters
     *
     * @param string $taxonomy Taxonomy to get terms for
     * @param array $tax_queries Tax queries to filter posts
     * @return array Array of term IDs
     */
    private function getTermsFromFilteredPosts(string $taxonomy, array $tax_queries): array
    {
        // Query for posts matching tax queries
        $args = [
            'post_type' => 'lowongan',
            'post_status' => 'publish',
            'fields' => 'ids', // Only get post IDs
            'posts_per_page' => -1, // Get all matching posts
            'tax_query' => [
                'relation' => 'AND',
                $tax_queries
            ]
        ];

        $posts = get_posts($args);

        if (empty($posts)) {
            return [0]; // No posts match, return dummy ID to force no results
        }

        // Get all terms used in these posts
        $term_ids = [];
        foreach ($posts as $post_id) {
            $terms = get_the_terms($post_id, $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $term_ids[$term->term_id] = $term->term_id;
                }
            }
        }

        return array_values($term_ids);
    }
}