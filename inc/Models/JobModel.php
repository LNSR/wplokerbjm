<?php

namespace AstraChild\Models;
use AstraChild\Helpers\JobHelpers;
use AstraChild\Helpers\TaxonomyHelpers;
use AstraChild\Models\TaxonomyModel;

/**
 * Job Model
 * 
 * Handles job data operations
 */
class JobModel
{
    /**
     * @var TaxonomyModel
     */
    protected $taxonomyModel;

    /**
     * Initialize the Job Model
     */
    public function __construct(?TaxonomyModel $taxonomyModel = null)
    {
        $this->taxonomyModel = $taxonomyModel ?? new TaxonomyModel();
    }

    /**
     * Get the taxonomy model
     * 
     * @return TaxonomyModel
     */
    public function getTaxonomyModel()
    {
        return $this->taxonomyModel;
    }

    /**
     * Get job meta data
     *
     * @param int|null $post_id Post ID or null for current post
     * @return array Job metadata
     */
    public function getJobMetaData($post_id = null)
    {
        // Get taxonomy terms as arrays first
        $location_terms = wp_get_post_terms($post_id, 'lokasi-pekerjaan');
        $job_type_terms = wp_get_post_terms($post_id, 'jenis-pekerjaan');
        $education_terms = wp_get_post_terms($post_id, 'pendidikan');
        $gender_terms = wp_get_post_terms($post_id, 'gender');
        
        // Format them both as strings and keep arrays
        $location = !empty($location_terms) ? $location_terms[0]->name : '';
        $location_all = TaxonomyHelpers::formatTermsToString($location_terms);
        
        $job_type = !empty($job_type_terms) ? $job_type_terms[0]->name : '';
        $job_type_all = TaxonomyHelpers::formatTermsToString($job_type_terms);
        
        $education = !empty($education_terms) ? $education_terms[0]->name : '';
        $education_all = TaxonomyHelpers::formatTermsToString($education_terms);
        
        $gender = !empty($gender_terms) ? $gender_terms[0]->name : '';
        $gender_all = TaxonomyHelpers::formatTermsToString($gender_terms);
        
        return [
            'company' => rwmb_meta('nama_perusahaan', '', $post_id),
            'company_desc' => rwmb_meta('tentang_perusahaan', '', $post_id),
            'job_desc' => rwmb_meta('deskripsi_pekerjaan', '', $post_id),
            
            // Single term values (for backward compatibility)
            // 'education' => $education,
            // 'job_type' => $job_type,
            // 'gender' => $gender,
            // 'location' => $location,
            
            // Comma-separated strings of all terms
            'education' => $education_all,
            'job_type' => $job_type_all,
            'gender' => $gender_all,
            'location' => $location_all,
            
            // Raw term arrays for advanced usage
            'education_terms' => $education_terms,
            'job_type_terms' => $job_type_terms,
            'gender_terms' => $gender_terms,
            'location_terms' => $location_terms,
            
            'min_age' => rwmb_meta('umur_min', '', $post_id),
            'max_age' => rwmb_meta('umur_max', '', $post_id),
            'experience' => rwmb_meta('pengalaman', '', $post_id),
            'requirements' => rwmb_meta('persyaratan', '', $post_id),
            'min_salary' => rwmb_meta('gaji_minimal', '', $post_id),
            'max_salary' => rwmb_meta('gaji_maksimal', '', $post_id),
            'deadline' => rwmb_meta('deadline', '', $post_id),
            'email' => rwmb_meta('email_kontak', '', $post_id),
            'phone' => rwmb_meta('nomor_kontak', '', $post_id),
            'website' => rwmb_meta('situs_kontak', '', $post_id),
            'socials' => rwmb_meta('social_media', '', $post_id),
            'status' => rwmb_meta('status_pekerjaan', '', $post_id)
        ];
    }

    /**
     * Get a single job with all its data
     *
     * @param int $post_id Post ID
     * @return array|null Job data or null if not found
     */
    public function getJob($post_id)
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'lowongan') {
            return null;
        }

        $meta_data = $this->getJobMetaData($post_id);

        return [
            'id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'date' => $post->post_date,
            'permalink' => get_permalink($post_id),
            'meta' => $meta_data
        ];
    }

    /**
     * Get featured jobs
     * 
     * @param int $page Current page number
     * @param array $filters Optional filter criteria
     * @param int $per_page Posts per page
     * @return array Query results and pagination data
     */
    public function getFeaturedJobs($page = 1, $filters = [], $per_page = 10)
    {
        $args = [
            'post_type' => 'lowongan',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'paged' => $page
        ];

        $query = new \WP_Query($args);

        return [
            'query' => $query,
            'max_pages' => $query->max_num_pages,
            'current_page' => $page,
            'found_posts' => $query->found_posts
        ];
    }

    /**
     * Search jobs based on criteria
     *
     * @param array $params Search parameters
     * @param int $page Current page number
     * @return array Search results and pagination data
     */
    public function searchJobs($params = [], $page = 1)
    {
        // Default search args
        $args = [
            'post_type' => 'lowongan',
            'posts_per_page' => 10,
            'paged' => $page
        ];

        // Add search query if provided
        if (!empty($params['keywords'])) {
            $args['s'] = sanitize_text_field($params['keywords']);
        }

        // Add taxonomy queries
        $tax_query = [];

        // Map frontend params to taxonomies
        $tax_mappings = [
            'location' => 'lokasi-pekerjaan',
            'job_type' => 'jenis-pekerjaan',
            'education' => 'pendidikan',
            'experience' => 'pengalaman',
            'gender' => 'gender',
            'salary' => 'gaji'
        ];

        foreach ($tax_mappings as $param => $taxonomy) {
            if (!empty($params[$param])) {
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => absint($params[$param])
                ];
            }
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = [
                'relation' => 'AND',
                $tax_query
            ];
        }

        $query = new \WP_Query($args);

        return [
            'query' => $query,
            'max_pages' => $query->max_num_pages,
            'current_page' => $page,
            'found_posts' => $query->found_posts
        ];
    }

    /**
     * Save a job to the database
     * 
     * @param JobEntity $jobEntity The job entity to save
     * @return int|false Post ID on success, false on failure
     */
    public function saveJob(JobEntity $jobEntity)
    {
        $data = $jobEntity->getAttributes();

        // Split data into post data and meta data
        $post_data = [
            'post_type' => 'lowongan',
            'post_title' => $data['title'] ?? '',
            'post_content' => $data['content'] ?? '',
            'post_status' => 'publish'
        ];

        if ($jobEntity->exists()) {
            $post_data['ID'] = $data['ID'];
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if (!$post_id || is_wp_error($post_id)) {
            return false;
        }

        $taxonomy_fields = [
            'education' => 'pendidikan',
            'job_type' => 'jenis-pekerjaan',
            'gender' => 'gender',
            'location' => 'lokasi-pekerjaan'
        ];
        
        // Set taxonomy terms
        foreach ($taxonomy_fields as $entity_key => $taxonomy) {
            if (isset($data[$entity_key])) {
                // Use TaxonomyModel instead of direct WP functions
                $term_id = $this->taxonomyModel->createTermIfNotExists($data[$entity_key], $taxonomy);
                
                if (!is_wp_error($term_id)) {
                    $this->taxonomyModel->setPostTerms($post_id, [$term_id], $taxonomy);
                }
            }
        }
        
        // Update meta fields (excluding taxonomy fields)
        $meta_fields = [
            'nama_perusahaan' => 'company',
            'tentang_perusahaan' => 'company_desc',
            'deskripsi_pekerjaan' => 'job_desc',
            'umur_min' => 'min_age',
            'umur_max' => 'max_age',
            'pengalaman' => 'experience',
            'persyaratan' => 'requirements',
            'gaji_minimal' => 'min_salary',
            'gaji_maksimal' => 'max_salary',
            'deadline' => 'deadline',
            'email_kontak' => 'email',
            'nomor_kontak' => 'phone',
            'situs_kontak' => 'website',
            'social_media' => 'socials',
            'status_pekerjaan' => 'status'
        ];
        
        foreach ($meta_fields as $meta_key => $entity_key) {
            if (isset($data[$entity_key])) {
                update_post_meta($post_id, $meta_key, $data[$entity_key]);
            }
        }

        return $post_id;
    }

    /**
     * Create a job entity from a post ID
     * 
     * @param int $post_id The job post ID
     * @return JobEntity|null Job entity or null if not found
     */
    public function createJobEntity($post_id)
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'lowongan') {
            return null;
        }

        // Get all meta data using our existing method
        $meta_data = $this->getJobMetaData($post_id);

        $entity = new JobEntity();

        // Set post data
        $entity->setAttribute('ID', $post_id);
        $entity->setAttribute('title', $post->post_title);
        $entity->setAttribute('content', $post->post_content);
        $entity->setAttribute('permalink', get_permalink($post_id));
        $entity->setAttribute('date', $post->post_date);

        // Add meta data as attributes
        foreach ($meta_data as $key => $value) {
            $entity->setAttribute($key, $value);
        }

        // Add view count
        $entity->setAttribute('job_view_count', get_post_meta($post_id, 'job_view_count', true));

        return $entity;
    }

    /**
     * Get recent jobs for sidebar widgets
     * 
     * @param int $count Number of jobs to get
     * @return array Query results
     */
    public function getRecentJobs($count = 5)
    {
        $args = [
            'post_type' => 'lowongan',
            'post_status' => 'publish',
            'posts_per_page' => $count,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $query = new \WP_Query($args);

        return [
            'query' => $query,
            'found_posts' => $query->found_posts
        ];
    }

    /**
     * Execute a job query with the given arguments - pure data access
     * 
     * @param array $args WP_Query arguments
     * @return array Query results and pagination data
     */
    public function executeJobQuery(array $args): array
    {
        $query = new \WP_Query($args);
        
        return [
            'query' => $query,
            'max_pages' => $query->max_num_pages,
            'current_page' => $args['paged'] ?? 1,
            'found_posts' => $query->found_posts
        ];
    }
}
