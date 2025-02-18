<?php

namespace AstraChild\Models\Schema;

/**
 * Taxonomies Schema
 * 
 * Defines custom taxonomies for job listings
 */
class Taxonomies
{
    /**
     * Register all taxonomies
     * 
     * @return void
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerPerusahaanTaxonomy']);
        add_action('init', [$this, 'registerKategoriTaxonomy']);
        add_action('init', [$this, 'registerLokasiTaxonomy']);
        add_action('init', [$this, 'registerJenisPekerjaanTaxonomy']);
        add_action('init', [$this, 'registerGenderTaxonomy']);
        add_action('init', [$this, 'registerPendidikanTaxonomy']);
    }

    /**
     * Register perusahaan taxonomy
     * 
     * @return void
     */
    public function registerPerusahaanTaxonomy(): void
    {
        $labels = [
            'name'                       => esc_html__('Perusahaan', 'astra-child'),
            'singular_name'              => esc_html__('Perusahaan', 'astra-child'),
            'menu_name'                  => esc_html__('Perusahaan', 'astra-child'),
            'search_items'               => esc_html__('Search Perusahaan', 'astra-child'),
            'popular_items'              => esc_html__('Popular Perusahaan', 'astra-child'),
            'all_items'                  => esc_html__('All Perusahaan', 'astra-child'),
            'parent_item'                => esc_html__('Parent Perusahaan', 'astra-child'),
            'parent_item_colon'          => esc_html__('Parent Perusahaan:', 'astra-child'),
            'edit_item'                  => esc_html__('Edit Perusahaan', 'astra-child'),
            'view_item'                  => esc_html__('View Perusahaan', 'astra-child'),
            'update_item'                => esc_html__('Update Perusahaan', 'astra-child'),
            'add_new_item'               => esc_html__('Add New Perusahaan', 'astra-child'),
            'new_item_name'              => esc_html__('New Perusahaan Name', 'astra-child'),
            'separate_items_with_commas' => esc_html__('Separate perusahaan with commas', 'astra-child'),
            'add_or_remove_items'        => esc_html__('Add or remove perusahaan', 'astra-child'),
            'choose_from_most_used'      => esc_html__('Choose most used perusahaan', 'astra-child'),
            'not_found'                  => esc_html__('No perusahaan found.', 'astra-child'),
            'no_terms'                   => esc_html__('No perusahaan', 'astra-child'),
            'filter_by_item'             => esc_html__('Filter by perusahaan', 'astra-child'),
            'items_list_navigation'      => esc_html__('Perusahaan list pagination', 'astra-child'),
            'items_list'                 => esc_html__('Perusahaan list', 'astra-child'),
            'most_used'                  => esc_html__('Most Used', 'astra-child'),
            'back_to_items'              => esc_html__('&larr; Go to Perusahaan', 'astra-child'),
            'text_domain'                => esc_html__('astra-child', 'astra-child'),
        ];
        $args = [
            'label'              => esc_html__('Perusahaan', 'astra-child'),
            'labels'             => $labels,
            'description'        => '',
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'show_tagcloud'      => true,
            'show_in_quick_edit' => true,
            'show_admin_column'  => true,
            'query_var'          => true,
            'sort'               => true,
            'capabilities'       => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ],
            'meta_box_cb'        => 'post_categories_meta_box',
            'rest_base'          => '',
            'rewrite'            => [
                'with_front'   => false,
                'hierarchical' => true,
            ],
        ];
        register_taxonomy('perusahaan', ['lowongan'], $args);
    }

    /**
     * Register kategori-lowongan taxonomy
     * 
     * @return void
     */
    public function registerKategoriTaxonomy(): void
    {
        $labels = [
            'name'                       => esc_html__('Kategori Pekerjaan', 'astra-child'),
            'singular_name'              => esc_html__('Kategori Pekerjaan', 'astra-child'),
            'menu_name'                  => esc_html__('Kategori Pekerjaan', 'astra-child'),
            'search_items'               => esc_html__('Search Kategori Pekerjaan', 'astra-child'),
            'popular_items'              => esc_html__('Popular Kategori Pekerjaan', 'astra-child'),
            'all_items'                  => esc_html__('All Kategori Pekerjaan', 'astra-child'),
            'parent_item'                => esc_html__('Parent Kategori Pekerjaan', 'astra-child'),
            'parent_item_colon'          => esc_html__('Parent Kategori Pekerjaan:', 'astra-child'),
            'edit_item'                  => esc_html__('Edit Kategori Pekerjaan', 'astra-child'),
            'view_item'                  => esc_html__('View Kategori Pekerjaan', 'astra-child'),
            'update_item'                => esc_html__('Update Kategori Pekerjaan', 'astra-child'),
            'add_new_item'               => esc_html__('Add New Kategori Pekerjaan', 'astra-child'),
            'new_item_name'              => esc_html__('New Kategori Pekerjaan Name', 'astra-child'),
            'separate_items_with_commas' => esc_html__('Separate kategori pekerjaan with commas', 'astra-child'),
            'add_or_remove_items'        => esc_html__('Add or remove kategori pekerjaan', 'astra-child'),
            'choose_from_most_used'      => esc_html__('Choose most used kategori pekerjaan', 'astra-child'),
            'not_found'                  => esc_html__('No kategori pekerjaan found.', 'astra-child'),
            'no_terms'                   => esc_html__('No kategori pekerjaan', 'astra-child'),
            'filter_by_item'             => esc_html__('Filter by kategori pekerjaan', 'astra-child'),
            'items_list_navigation'      => esc_html__('Kategori Pekerjaan list pagination', 'astra-child'),
            'items_list'                 => esc_html__('Kategori Pekerjaan list', 'astra-child'),
            'most_used'                  => esc_html__('Most Used', 'astra-child'),
            'back_to_items'              => esc_html__('&larr; Go to Kategori Pekerjaan', 'astra-child'),
        ];

        $args = [
            'label'              => esc_html__('Kategori Pekerjaan', 'astra-child'),
            'labels'             => $labels,
            'description'        => '',
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'show_tagcloud'      => false,
            'show_in_quick_edit' => false,
            'show_admin_column'  => true,
            'query_var'          => true,
            'sort'               => false,
            'capabilities'       => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ],
            'meta_box_cb'        => 'post_categories_meta_box',
            'rest_base'          => '',
            'rewrite'            => [
                'slug'         => 'kategori-lowongan',
                'with_front'   => false,
                'hierarchical' => true,
            ],
        ];

        register_taxonomy('kategori-lowongan', ['lowongan'], $args);
    }

    /**
     * Register lokasi-pekerjaan taxonomy
     * 
     * @return void
     */
    public function registerLokasiTaxonomy(): void
    {
        $labels = [
            'name'                       => esc_html__('Lokasi Pekerjaan', 'astra-child'),
            'singular_name'              => esc_html__('Lokasi Pekerjaan', 'astra-child'),
            'menu_name'                  => esc_html__('Lokasi Pekerjaan', 'astra-child'),
            'search_items'               => esc_html__('Search Lokasi Pekerjaan', 'astra-child'),
            'popular_items'              => esc_html__('Popular Lokasi Pekerjaan', 'astra-child'),
            'all_items'                  => esc_html__('All Lokasi Pekerjaan', 'astra-child'),
            'parent_item'                => esc_html__('Parent Lokasi Pekerjaan', 'astra-child'),
            'parent_item_colon'          => esc_html__('Parent Lokasi Pekerjaan:', 'astra-child'),
            'edit_item'                  => esc_html__('Edit Lokasi Pekerjaan', 'astra-child'),
            'view_item'                  => esc_html__('View Lokasi Pekerjaan', 'astra-child'),
            'update_item'                => esc_html__('Update Lokasi Pekerjaan', 'astra-child'),
            'add_new_item'               => esc_html__('Add New Lokasi Pekerjaan', 'astra-child'),
            'new_item_name'              => esc_html__('New Lokasi Pekerjaan Name', 'astra-child'),
            'separate_items_with_commas' => esc_html__('Separate lokasi pekerjaan with commas', 'astra-child'),
            'add_or_remove_items'        => esc_html__('Add or remove lokasi pekerjaan', 'astra-child'),
            'choose_from_most_used'      => esc_html__('Choose most used lokasi pekerjaan', 'astra-child'),
            'not_found'                  => esc_html__('No lokasi pekerjaan found.', 'astra-child'),
            'no_terms'                   => esc_html__('No lokasi pekerjaan', 'astra-child'),
            'filter_by_item'             => esc_html__('Filter by lokasi pekerjaan', 'astra-child'),
            'items_list_navigation'      => esc_html__('Lokasi Pekerjaan list pagination', 'astra-child'),
            'items_list'                 => esc_html__('Lokasi Pekerjaan list', 'astra-child'),
            'most_used'                  => esc_html__('Most Used', 'astra-child'),
            'back_to_items'              => esc_html__('&larr; Go to Lokasi Pekerjaan', 'astra-child'),
        ];

        $args = [
            'label'              => esc_html__('Lokasi Pekerjaan', 'astra-child'),
            'labels'             => $labels,
            'description'        => '',
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'show_tagcloud'      => false,
            'show_in_quick_edit' => false,
            'show_admin_column'  => true,
            'query_var'          => true,
            'sort'               => false,
            'capabilities'       => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ],
            'meta_box_cb'        => 'post_tags_meta_box',
            'rest_base'          => '',
            'rewrite'            => [
                'slug'         => 'lokasi-pekerjaan',
                'with_front'   => false,
                'hierarchical' => true,
            ],
        ];

        register_taxonomy('lokasi-pekerjaan', ['lowongan'], $args);
    }

    /**
     * Register jenis-pekerjaan taxonomy
     * 
     * @return void
     */
    public function registerJenisPekerjaanTaxonomy(): void
    {
        $labels = [
            'name'                       => esc_html__('Jenis Pekerjaan', 'astra-child'),
            'singular_name'              => esc_html__('Jenis Pekerjaan', 'astra-child'),
            'menu_name'                  => esc_html__('Jenis Pekerjaan', 'astra-child'),
            'search_items'               => esc_html__('Search Jenis Pekerjaan', 'astra-child'),
            'popular_items'              => esc_html__('Popular Jenis Pekerjaan', 'astra-child'),
            'all_items'                  => esc_html__('All Jenis Pekerjaan', 'astra-child'),
            'parent_item'                => esc_html__('Parent Jenis Pekerjaan', 'astra-child'),
            'parent_item_colon'          => esc_html__('Parent Jenis Pekerjaan:', 'astra-child'),
            'edit_item'                  => esc_html__('Edit Jenis Pekerjaan', 'astra-child'),
            'view_item'                  => esc_html__('View Jenis Pekerjaan', 'astra-child'),
            'update_item'                => esc_html__('Update Jenis Pekerjaan', 'astra-child'),
            'add_new_item'               => esc_html__('Add New Jenis Pekerjaan', 'astra-child'),
            'new_item_name'              => esc_html__('New Jenis Pekerjaan Name', 'astra-child'),
            'separate_items_with_commas' => esc_html__('Separate jenis pekerjaan with commas', 'astra-child'),
            'add_or_remove_items'        => esc_html__('Add or remove jenis pekerjaan', 'astra-child'),
            'choose_from_most_used'      => esc_html__('Choose most used jenis pekerjaan', 'astra-child'),
            'not_found'                  => esc_html__('No jenis pekerjaan found.', 'astra-child'),
            'no_terms'                   => esc_html__('No jenis pekerjaan', 'astra-child'),
            'filter_by_item'             => esc_html__('Filter by jenis pekerjaan', 'astra-child'),
            'items_list_navigation'      => esc_html__('Jenis Pekerjaan list pagination', 'astra-child'),
            'items_list'                 => esc_html__('Jenis Pekerjaan list', 'astra-child'),
            'most_used'                  => esc_html__('Most Used', 'astra-child'),
            'back_to_items'              => esc_html__('&larr; Go to Jenis Pekerjaan', 'astra-child'),
        ];

        $args = [
            'label'              => esc_html__('Jenis Pekerjaan', 'astra-child'),
            'labels'             => $labels,
            'description'        => '',
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'show_tagcloud'      => false,
            'show_in_quick_edit' => true,
            'show_admin_column'  => true,
            'query_var'          => true,
            'sort'               => false,
            'capabilities'       => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ],
            'meta_box_cb'        => 'post_categories_meta_box',
            'rest_base'          => '',
            'rewrite'            => [
                'slug'         => 'jenis-pekerjaan',
                'with_front'   => false,
                'hierarchical' => true,
            ],
        ];

        register_taxonomy('jenis-pekerjaan', ['lowongan'], $args);
    }

    /**
     * Register gender taxonomy
     * 
     * @return void
     */
    public function registerGenderTaxonomy(): void
    {
        $labels = [
            'name'                       => esc_html__('Gender', 'astra-child'),
            'singular_name'              => esc_html__('Gender', 'astra-child'),
            'menu_name'                  => esc_html__('Gender', 'astra-child'),
            'search_items'               => esc_html__('Search Gender', 'astra-child'),
            'popular_items'              => esc_html__('Popular Gender', 'astra-child'),
            'all_items'                  => esc_html__('All Gender', 'astra-child'),
            'parent_item'                => esc_html__('Parent Gender', 'astra-child'),
            'parent_item_colon'          => esc_html__('Parent Gender:', 'astra-child'),
            'edit_item'                  => esc_html__('Edit Gender', 'astra-child'),
            'view_item'                  => esc_html__('View Gender', 'astra-child'),
            'update_item'                => esc_html__('Update Gender', 'astra-child'),
            'add_new_item'               => esc_html__('Add New Gender', 'astra-child'),
            'new_item_name'              => esc_html__('New Gender Name', 'astra-child'),
            'separate_items_with_commas' => esc_html__('Separate gender with commas', 'astra-child'),
            'add_or_remove_items'        => esc_html__('Add or remove gender', 'astra-child'),
            'choose_from_most_used'      => esc_html__('Choose most used gender', 'astra-child'),
            'not_found'                  => esc_html__('No gender found.', 'astra-child'),
            'no_terms'                   => esc_html__('No gender', 'astra-child'),
            'filter_by_item'             => esc_html__('Filter by gender', 'astra-child'),
            'items_list_navigation'      => esc_html__('Gender list pagination', 'astra-child'),
            'items_list'                 => esc_html__('Gender list', 'astra-child'),
            'most_used'                  => esc_html__('Most Used', 'astra-child'),
            'back_to_items'              => esc_html__('&larr; Go to Gender', 'astra-child'),
        ];

        $args = [
            'label'              => esc_html__('Gender', 'astra-child'),
            'labels'             => $labels,
            'description'        => '',
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'show_tagcloud'      => false,
            'show_in_quick_edit' => true,
            'show_admin_column'  => true,
            'query_var'          => true,
            'sort'               => false,
            'capabilities'       => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ],
            'meta_box_cb'        => 'post_categories_meta_box',
            'rest_base'          => '',
            'rewrite'            => [
                'slug'         => 'gender',
                'with_front'   => false,
                'hierarchical' => false,
            ],
        ];

        register_taxonomy('gender', ['lowongan'], $args);
    }

    /**
     * Register pendidikan taxonomy
     * 
     * @return void
     */
    public function registerPendidikanTaxonomy(): void
    {
        $labels = [
            'name'                       => esc_html__('Pendidikan', 'astra-child'),
            'singular_name'              => esc_html__('Pendidikan', 'astra-child'),
            'menu_name'                  => esc_html__('Pendidikan', 'astra-child'),
            'search_items'               => esc_html__('Search Pendidikan', 'astra-child'),
            'popular_items'              => esc_html__('Popular Pendidikan', 'astra-child'),
            'all_items'                  => esc_html__('All Pendidikan', 'astra-child'),
            'parent_item'                => esc_html__('Parent Pendidikan', 'astra-child'),
            'parent_item_colon'          => esc_html__('Parent Pendidikan:', 'astra-child'),
            'edit_item'                  => esc_html__('Edit Pendidikan', 'astra-child'),
            'view_item'                  => esc_html__('View Pendidikan', 'astra-child'),
            'update_item'                => esc_html__('Update Pendidikan', 'astra-child'),
            'add_new_item'               => esc_html__('Add New Pendidikan', 'astra-child'),
            'new_item_name'              => esc_html__('New Pendidikan Name', 'astra-child'),
            'separate_items_with_commas' => esc_html__('Separate pendidikan with commas', 'astra-child'),
            'add_or_remove_items'        => esc_html__('Add or remove pendidikan', 'astra-child'),
            'choose_from_most_used'      => esc_html__('Choose most used pendidikan', 'astra-child'),
            'not_found'                  => esc_html__('No pendidikan found.', 'astra-child'),
            'no_terms'                   => esc_html__('No pendidikan', 'astra-child'),
            'filter_by_item'             => esc_html__('Filter by pendidikan', 'astra-child'),
            'items_list_navigation'      => esc_html__('Pendidikan list pagination', 'astra-child'),
            'items_list'                 => esc_html__('Pendidikan list', 'astra-child'),
            'most_used'                  => esc_html__('Most Used', 'astra-child'),
            'back_to_items'              => esc_html__('&larr; Go to Pendidikan', 'astra-child'),
        ];

        $args = [
            'label'              => esc_html__('Pendidikan', 'astra-child'),
            'labels'             => $labels,
            'description'        => '',
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'show_tagcloud'      => false,
            'show_in_quick_edit' => true,
            'show_admin_column'  => true,
            'query_var'          => true,
            'sort'               => false,
            'capabilities'       => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ],
            'meta_box_cb'        => 'post_categories_meta_box',
            'rest_base'          => '',
            'rewrite'            => [
                'slug'         => 'pendidikan',
                'with_front'   => false,
                'hierarchical' => true,
            ],
        ];

        register_taxonomy('pendidikan', ['lowongan'], $args);
    }
}
