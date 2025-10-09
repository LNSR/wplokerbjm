<?php

namespace WPLokerBJM\Models\Schema;
use WPLokerBJM\Contracts\HooksInterface;

/**
 * Taxonomies Schema
 *
 * Defines custom taxonomies for job listings.
 *
 * @note This class serves as a blueprint/template for defining WordPress custom taxonomies.
 *       The actual source of truth for taxonomy configurations is this code. Changes here
 *       directly affect the registered taxonomies in WordPress.
 *
 * @package WPLokerBJM\Models\Schema
 */
class Taxonomies implements HooksInterface
{
    /**
     * Register all taxonomies
     * 
     * @return void
     */
    public function registerActions(): void
    {
        add_action('init', [$this, 'registerPerusahaanTaxonomy']);
        add_action('init', [$this, 'registerKategoriTaxonomy']);
        add_action('init', [$this, 'registerLokasiTaxonomy']);
        add_action('init', [$this, 'registerJenisPekerjaanTaxonomy']);
        add_action('init', [$this, 'registerGenderTaxonomy']);
        add_action('init', [$this, 'registerPendidikanTaxonomy']);
    }
    public function registerFilters(): void
    {
        // No filters to register in this class
    }

    /**
     * Register perusahaan taxonomy
     * 
     * @return void
     */
    public function registerPerusahaanTaxonomy(): void
    {
        $labels = [
            'name'                       => esc_html__('Perusahaan', 'wplokerbjm'),
            'singular_name'              => esc_html__('Perusahaan', 'wplokerbjm'),
            'menu_name'                  => esc_html__('Perusahaan', 'wplokerbjm'),
            'search_items'               => esc_html__('Search Perusahaan', 'wplokerbjm'),
            'popular_items'              => esc_html__('Popular Perusahaan', 'wplokerbjm'),
            'all_items'                  => esc_html__('All Perusahaan', 'wplokerbjm'),
            'parent_item'                => esc_html__('Parent Perusahaan', 'wplokerbjm'),
            'parent_item_colon'          => esc_html__('Parent Perusahaan:', 'wplokerbjm'),
            'edit_item'                  => esc_html__('Edit Perusahaan', 'wplokerbjm'),
            'view_item'                  => esc_html__('View Perusahaan', 'wplokerbjm'),
            'update_item'                => esc_html__('Update Perusahaan', 'wplokerbjm'),
            'add_new_item'               => esc_html__('Add New Perusahaan', 'wplokerbjm'),
            'new_item_name'              => esc_html__('New Perusahaan Name', 'wplokerbjm'),
            'separate_items_with_commas' => esc_html__('Separate perusahaan with commas', 'wplokerbjm'),
            'add_or_remove_items'        => esc_html__('Add or remove perusahaan', 'wplokerbjm'),
            'choose_from_most_used'      => esc_html__('Choose most used perusahaan', 'wplokerbjm'),
            'not_found'                  => esc_html__('No perusahaan found.', 'wplokerbjm'),
            'no_terms'                   => esc_html__('No perusahaan', 'wplokerbjm'),
            'filter_by_item'             => esc_html__('Filter by perusahaan', 'wplokerbjm'),
            'items_list_navigation'      => esc_html__('Perusahaan list pagination', 'wplokerbjm'),
            'items_list'                 => esc_html__('Perusahaan list', 'wplokerbjm'),
            'most_used'                  => esc_html__('Most Used', 'wplokerbjm'),
            'back_to_items'              => esc_html__('&larr; Go to Perusahaan', 'wplokerbjm'),
            'text_domain'                => esc_html__('wplokerbjm', 'wplokerbjm'),
        ];
        $args = [
            'label'              => esc_html__('Perusahaan', 'wplokerbjm'),
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
            'name'                       => esc_html__('Kategori Pekerjaan', 'wplokerbjm'),
            'singular_name'              => esc_html__('Kategori Pekerjaan', 'wplokerbjm'),
            'menu_name'                  => esc_html__('Kategori Pekerjaan', 'wplokerbjm'),
            'search_items'               => esc_html__('Search Kategori Pekerjaan', 'wplokerbjm'),
            'popular_items'              => esc_html__('Popular Kategori Pekerjaan', 'wplokerbjm'),
            'all_items'                  => esc_html__('All Kategori Pekerjaan', 'wplokerbjm'),
            'parent_item'                => esc_html__('Parent Kategori Pekerjaan', 'wplokerbjm'),
            'parent_item_colon'          => esc_html__('Parent Kategori Pekerjaan:', 'wplokerbjm'),
            'edit_item'                  => esc_html__('Edit Kategori Pekerjaan', 'wplokerbjm'),
            'view_item'                  => esc_html__('View Kategori Pekerjaan', 'wplokerbjm'),
            'update_item'                => esc_html__('Update Kategori Pekerjaan', 'wplokerbjm'),
            'add_new_item'               => esc_html__('Add New Kategori Pekerjaan', 'wplokerbjm'),
            'new_item_name'              => esc_html__('New Kategori Pekerjaan Name', 'wplokerbjm'),
            'separate_items_with_commas' => esc_html__('Separate kategori pekerjaan with commas', 'wplokerbjm'),
            'add_or_remove_items'        => esc_html__('Add or remove kategori pekerjaan', 'wplokerbjm'),
            'choose_from_most_used'      => esc_html__('Choose most used kategori pekerjaan', 'wplokerbjm'),
            'not_found'                  => esc_html__('No kategori pekerjaan found.', 'wplokerbjm'),
            'no_terms'                   => esc_html__('No kategori pekerjaan', 'wplokerbjm'),
            'filter_by_item'             => esc_html__('Filter by kategori pekerjaan', 'wplokerbjm'),
            'items_list_navigation'      => esc_html__('Kategori Pekerjaan list pagination', 'wplokerbjm'),
            'items_list'                 => esc_html__('Kategori Pekerjaan list', 'wplokerbjm'),
            'most_used'                  => esc_html__('Most Used', 'wplokerbjm'),
            'back_to_items'              => esc_html__('&larr; Go to Kategori Pekerjaan', 'wplokerbjm'),
        ];

        $args = [
            'label'              => esc_html__('Kategori Pekerjaan', 'wplokerbjm'),
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
            'name'                       => esc_html__('Lokasi Pekerjaan', 'wplokerbjm'),
            'singular_name'              => esc_html__('Lokasi Pekerjaan', 'wplokerbjm'),
            'menu_name'                  => esc_html__('Lokasi Pekerjaan', 'wplokerbjm'),
            'search_items'               => esc_html__('Search Lokasi Pekerjaan', 'wplokerbjm'),
            'popular_items'              => esc_html__('Popular Lokasi Pekerjaan', 'wplokerbjm'),
            'all_items'                  => esc_html__('All Lokasi Pekerjaan', 'wplokerbjm'),
            'parent_item'                => esc_html__('Parent Lokasi Pekerjaan', 'wplokerbjm'),
            'parent_item_colon'          => esc_html__('Parent Lokasi Pekerjaan:', 'wplokerbjm'),
            'edit_item'                  => esc_html__('Edit Lokasi Pekerjaan', 'wplokerbjm'),
            'view_item'                  => esc_html__('View Lokasi Pekerjaan', 'wplokerbjm'),
            'update_item'                => esc_html__('Update Lokasi Pekerjaan', 'wplokerbjm'),
            'add_new_item'               => esc_html__('Add New Lokasi Pekerjaan', 'wplokerbjm'),
            'new_item_name'              => esc_html__('New Lokasi Pekerjaan Name', 'wplokerbjm'),
            'separate_items_with_commas' => esc_html__('Separate lokasi pekerjaan with commas', 'wplokerbjm'),
            'add_or_remove_items'        => esc_html__('Add or remove lokasi pekerjaan', 'wplokerbjm'),
            'choose_from_most_used'      => esc_html__('Choose most used lokasi pekerjaan', 'wplokerbjm'),
            'not_found'                  => esc_html__('No lokasi pekerjaan found.', 'wplokerbjm'),
            'no_terms'                   => esc_html__('No lokasi pekerjaan', 'wplokerbjm'),
            'filter_by_item'             => esc_html__('Filter by lokasi pekerjaan', 'wplokerbjm'),
            'items_list_navigation'      => esc_html__('Lokasi Pekerjaan list pagination', 'wplokerbjm'),
            'items_list'                 => esc_html__('Lokasi Pekerjaan list', 'wplokerbjm'),
            'most_used'                  => esc_html__('Most Used', 'wplokerbjm'),
            'back_to_items'              => esc_html__('&larr; Go to Lokasi Pekerjaan', 'wplokerbjm'),
        ];

        $args = [
            'label'              => esc_html__('Lokasi Pekerjaan', 'wplokerbjm'),
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
            'name'                       => esc_html__('Jenis Pekerjaan', 'wplokerbjm'),
            'singular_name'              => esc_html__('Jenis Pekerjaan', 'wplokerbjm'),
            'menu_name'                  => esc_html__('Jenis Pekerjaan', 'wplokerbjm'),
            'search_items'               => esc_html__('Search Jenis Pekerjaan', 'wplokerbjm'),
            'popular_items'              => esc_html__('Popular Jenis Pekerjaan', 'wplokerbjm'),
            'all_items'                  => esc_html__('All Jenis Pekerjaan', 'wplokerbjm'),
            'parent_item'                => esc_html__('Parent Jenis Pekerjaan', 'wplokerbjm'),
            'parent_item_colon'          => esc_html__('Parent Jenis Pekerjaan:', 'wplokerbjm'),
            'edit_item'                  => esc_html__('Edit Jenis Pekerjaan', 'wplokerbjm'),
            'view_item'                  => esc_html__('View Jenis Pekerjaan', 'wplokerbjm'),
            'update_item'                => esc_html__('Update Jenis Pekerjaan', 'wplokerbjm'),
            'add_new_item'               => esc_html__('Add New Jenis Pekerjaan', 'wplokerbjm'),
            'new_item_name'              => esc_html__('New Jenis Pekerjaan Name', 'wplokerbjm'),
            'separate_items_with_commas' => esc_html__('Separate jenis pekerjaan with commas', 'wplokerbjm'),
            'add_or_remove_items'        => esc_html__('Add or remove jenis pekerjaan', 'wplokerbjm'),
            'choose_from_most_used'      => esc_html__('Choose most used jenis pekerjaan', 'wplokerbjm'),
            'not_found'                  => esc_html__('No jenis pekerjaan found.', 'wplokerbjm'),
            'no_terms'                   => esc_html__('No jenis pekerjaan', 'wplokerbjm'),
            'filter_by_item'             => esc_html__('Filter by jenis pekerjaan', 'wplokerbjm'),
            'items_list_navigation'      => esc_html__('Jenis Pekerjaan list pagination', 'wplokerbjm'),
            'items_list'                 => esc_html__('Jenis Pekerjaan list', 'wplokerbjm'),
            'most_used'                  => esc_html__('Most Used', 'wplokerbjm'),
            'back_to_items'              => esc_html__('&larr; Go to Jenis Pekerjaan', 'wplokerbjm'),
        ];

        $args = [
            'label'              => esc_html__('Jenis Pekerjaan', 'wplokerbjm'),
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
            'name'                       => esc_html__('Gender', 'wplokerbjm'),
            'singular_name'              => esc_html__('Gender', 'wplokerbjm'),
            'menu_name'                  => esc_html__('Gender', 'wplokerbjm'),
            'search_items'               => esc_html__('Search Gender', 'wplokerbjm'),
            'popular_items'              => esc_html__('Popular Gender', 'wplokerbjm'),
            'all_items'                  => esc_html__('All Gender', 'wplokerbjm'),
            'parent_item'                => esc_html__('Parent Gender', 'wplokerbjm'),
            'parent_item_colon'          => esc_html__('Parent Gender:', 'wplokerbjm'),
            'edit_item'                  => esc_html__('Edit Gender', 'wplokerbjm'),
            'view_item'                  => esc_html__('View Gender', 'wplokerbjm'),
            'update_item'                => esc_html__('Update Gender', 'wplokerbjm'),
            'add_new_item'               => esc_html__('Add New Gender', 'wplokerbjm'),
            'new_item_name'              => esc_html__('New Gender Name', 'wplokerbjm'),
            'separate_items_with_commas' => esc_html__('Separate gender with commas', 'wplokerbjm'),
            'add_or_remove_items'        => esc_html__('Add or remove gender', 'wplokerbjm'),
            'choose_from_most_used'      => esc_html__('Choose most used gender', 'wplokerbjm'),
            'not_found'                  => esc_html__('No gender found.', 'wplokerbjm'),
            'no_terms'                   => esc_html__('No gender', 'wplokerbjm'),
            'filter_by_item'             => esc_html__('Filter by gender', 'wplokerbjm'),
            'items_list_navigation'      => esc_html__('Gender list pagination', 'wplokerbjm'),
            'items_list'                 => esc_html__('Gender list', 'wplokerbjm'),
            'most_used'                  => esc_html__('Most Used', 'wplokerbjm'),
            'back_to_items'              => esc_html__('&larr; Go to Gender', 'wplokerbjm'),
        ];

        $args = [
            'label'              => esc_html__('Gender', 'wplokerbjm'),
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
            'name'                       => esc_html__('Pendidikan', 'wplokerbjm'),
            'singular_name'              => esc_html__('Pendidikan', 'wplokerbjm'),
            'menu_name'                  => esc_html__('Pendidikan', 'wplokerbjm'),
            'search_items'               => esc_html__('Search Pendidikan', 'wplokerbjm'),
            'popular_items'              => esc_html__('Popular Pendidikan', 'wplokerbjm'),
            'all_items'                  => esc_html__('All Pendidikan', 'wplokerbjm'),
            'parent_item'                => esc_html__('Parent Pendidikan', 'wplokerbjm'),
            'parent_item_colon'          => esc_html__('Parent Pendidikan:', 'wplokerbjm'),
            'edit_item'                  => esc_html__('Edit Pendidikan', 'wplokerbjm'),
            'view_item'                  => esc_html__('View Pendidikan', 'wplokerbjm'),
            'update_item'                => esc_html__('Update Pendidikan', 'wplokerbjm'),
            'add_new_item'               => esc_html__('Add New Pendidikan', 'wplokerbjm'),
            'new_item_name'              => esc_html__('New Pendidikan Name', 'wplokerbjm'),
            'separate_items_with_commas' => esc_html__('Separate pendidikan with commas', 'wplokerbjm'),
            'add_or_remove_items'        => esc_html__('Add or remove pendidikan', 'wplokerbjm'),
            'choose_from_most_used'      => esc_html__('Choose most used pendidikan', 'wplokerbjm'),
            'not_found'                  => esc_html__('No pendidikan found.', 'wplokerbjm'),
            'no_terms'                   => esc_html__('No pendidikan', 'wplokerbjm'),
            'filter_by_item'             => esc_html__('Filter by pendidikan', 'wplokerbjm'),
            'items_list_navigation'      => esc_html__('Pendidikan list pagination', 'wplokerbjm'),
            'items_list'                 => esc_html__('Pendidikan list', 'wplokerbjm'),
            'most_used'                  => esc_html__('Most Used', 'wplokerbjm'),
            'back_to_items'              => esc_html__('&larr; Go to Pendidikan', 'wplokerbjm'),
        ];

        $args = [
            'label'              => esc_html__('Pendidikan', 'wplokerbjm'),
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
