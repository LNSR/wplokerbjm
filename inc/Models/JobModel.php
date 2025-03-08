<?php

namespace AstraChild\Models;

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
    public function __construct()
    {
        $this->taxonomyModel = new TaxonomyModel();
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
        return [
            'company' => rwmb_meta('nama_perusahaan', '', $post_id),
            'company_desc' => rwmb_meta('tentang_perusahaan', '', $post_id),
            'job_desc' => rwmb_meta('deskripsi_pekerjaan', '', $post_id),
            'education' => rwmb_meta('pendidikan', '', $post_id),
            'job_type' => rwmb_meta('jenis_pekerjaan', '', $post_id),
            'gender' => rwmb_meta('gender', '', $post_id),
            'min_age' => rwmb_meta('umur_min', '', $post_id),
            'max_age' => rwmb_meta('umur_max', '', $post_id),
            'experience' => rwmb_meta('pengalaman', '', $post_id),
            'requirements' => rwmb_meta('persyaratan', '', $post_id),
            'min_salary' => rwmb_meta('gaji_minimal', '', $post_id),
            'max_salary' => rwmb_meta('gaji_maksimal', '', $post_id),
            'location' => rwmb_meta('lokasi', '', $post_id),
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
     * @param int $per_page Posts per page
     * @return array Query results and pagination data
     */
    public function getFeaturedJobs($page = 1, $per_page = 6)
    {
        $args = [
            'post_type' => 'lowongan',
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
     * Get jobs by status for carousel
     * 
     * @return array Array of jobs for status carousel
     */
    public function getStatusCarouselJobs()
    {
        $output = [];
        $status_priority = ['4', '3', '2'];

        foreach ($status_priority as $status) {
            $args = [
                'post_type' => 'lowongan',
                'posts_per_page' => 10,
                'meta_key' => 'status_pekerjaan',
                'meta_value' => $status,
                'orderby' => 'date',
                'order' => 'DESC'
            ];

            $query = new \WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $job_data = $this->getJobMetaData(get_the_ID());

                    // Get status attributes
                    $status_attrs = $this->getJobStatusAttributes($status);

                    // Generate border class based on text color class
                    $border_class = !empty($status_attrs['class']) ?
                        ' border-' . substr(strstr($status_attrs['class'], 'text-'), 5, -4) . '-200' : '';

                    $output[] = [
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'permalink' => get_permalink(),
                        'company' => $job_data['company'],
                        'location' => $job_data['location'],
                        'deadline' => $job_data['deadline'],
                        'status' => [
                            'label' => $status_attrs['label'],
                            'icon' => $status_attrs['icon'],
                            'class' => $status_attrs['class'] . $border_class
                        ]
                    ];
                }
                wp_reset_postdata();
            }
        }

        // Add normal jobs if needed to fill carousel
        if (count($output) < 3) {
            $this->fillCarouselWithNormalJobs($output);
        }

        return $output;
    }

    /**
     * Fill carousel with normal jobs if needed
     * 
     * @param array &$output Reference to output array
     * @return void
     */
    private function fillCarouselWithNormalJobs(&$output)
    {
        $args = [
            'post_type' => 'lowongan',
            'posts_per_page' => 3 - count($output),
            'meta_key' => 'status_pekerjaan',
            'meta_value' => '0',
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $job_data = $this->getJobMetaData(get_the_ID());

                $output[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'company' => $job_data['company'],
                    'location' => $job_data['location'],
                    'status' => [
                        'label' => 'Recommended',
                        'icon' => 'fas fa-thumbs-up',
                        'class' => 'bg-blue-100 text-blue-800 border-blue-200'
                    ]
                ];
            }
            wp_reset_postdata();
        }
    }

    /**
     * Get job status attributes
     * 
     * @param string $status Status code
     * @return array Status attributes
     */
    public function getJobStatusAttributes($status)
    {
        $attributes = [
            '0' => [
                'label' => 'Normal',
                'class' => '',
                'icon' => 'fas fa-briefcase'
            ],
            '2' => [
                'label' => 'Urgent',
                'class' => 'bg-red-100 text-red-800',
                'icon' => 'fas fa-fire-alt'
            ],
            '3' => [
                'label' => 'Pinned',
                'class' => 'bg-yellow-100 text-yellow-800',
                'icon' => 'fas fa-thumbtack'
            ],
            '4' => [
                'label' => 'Pinned & Urgent',
                'class' => 'bg-orange-100 text-orange-800',
                'icon' => 'fas fa-exclamation-circle'
            ]
        ];

        return isset($attributes[$status]) ? $attributes[$status] : $attributes['0'];
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

        // Save meta data
        $meta_fields = [
            'nama_perusahaan' => 'company',
            'tentang_perusahaan' => 'company_desc',
            'deskripsi_pekerjaan' => 'job_desc',
            'pendidikan' => 'education',
            'jenis_pekerjaan' => 'job_type',
            'gender' => 'gender',
            'umur_min' => 'min_age',
            'umur_max' => 'max_age',
            'pengalaman' => 'experience',
            'persyaratan' => 'requirements',
            'gaji_minimal' => 'min_salary',
            'gaji_maksimal' => 'max_salary',
            'lokasi' => 'location',
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
     * Get search results based on request parameters
     * 
     * @param int $paged Current page number
     * @return array Query results and pagination data
     */
    public function getSearchResultsJobs(int $paged = 1): array
    {
        $args = [
            'post_type' => 'lowongan',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'paged' => $paged
        ];

        // Search by keywords
        $keywords = isset($_GET['keywords']) ? sanitize_text_field($_GET['keywords']) : '';
        if (!empty($keywords)) {
            $args['s'] = $keywords;
        }

        // Build taxonomy query
        $tax_query = [];

        // Location filter
        $location = isset($_GET['loc']) ? sanitize_text_field($_GET['loc']) : '';
        if (!empty($location)) {
            $tax_query[] = [
                'taxonomy' => 'lokasi-pekerjaan',
                'field' => 'slug',
                'terms' => $location
            ];
        }

        // Experience filter
        $experience = isset($_GET['pengalaman']) ? sanitize_text_field($_GET['pengalaman']) : '';
        if (!empty($experience)) {
            $tax_query[] = [
                'taxonomy' => 'pengalaman',
                'field' => 'slug',
                'terms' => $experience
            ];
        }

        // Education filter
        $education = isset($_GET['pendidikan']) ? sanitize_text_field($_GET['pendidikan']) : '';
        if (!empty($education)) {
            $tax_query[] = [
                'taxonomy' => 'pendidikan',
                'field' => 'slug',
                'terms' => $education
            ];
        }

        // Only add tax query if we have any taxonomy conditions
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
            'current_page' => $paged,
            'found_posts' => $query->found_posts
        ];
    }
}
