<?php
namespace WPLokerBJM\Models\Schema;

use WPLokerBJM\Contracts\HooksInterface;

/**
 * Post Types Schema
 *
 * Defines custom post types for the application.
 *
 * @note This class serves as a blueprint/template for defining WordPress custom post types.
 *       The actual source of truth for post type configurations is this code. Changes here
 *       directly affect the registered post types in WordPress. Post types are registered
 *       in-memory and not stored in the database by default.
 *
 * @package WPLokerBJM\Models\Schema
 */
class PostTypes implements HooksInterface {
    /**
     * Register all custom post types
     * 
     * @return void
     */
    public function registerActions(): void {
        add_action('init', [$this, 'registerLowonganPostType']);
    }

    public function registerFilters(): void {
        // No filters to register in this class
    }

    /**
     * Register the Lowongan post type
     * 
     * @return void
     */
    public function registerLowonganPostType(): void {
        $labels = [
            'name'                     => esc_html__('Lowongan Kerja', 'wplokerbjm'),
            'singular_name'            => esc_html__('Lowongan', 'wplokerbjm'),
            'add_new'                  => esc_html__('Tambah Baru', 'wplokerbjm'),
            'add_new_item'             => esc_html__('Tambah Lowongan Baru', 'wplokerbjm'),
            'edit_item'                => esc_html__('Edit Lowongan', 'wplokerbjm'),
            'new_item'                 => esc_html__('Lowongan Baru', 'wplokerbjm'),
            'view_item'                => esc_html__('Lihat Lowongan', 'wplokerbjm'),
            'view_items'               => esc_html__('Lihat Lowongan Kerja', 'wplokerbjm'),
            'search_items'             => esc_html__('Cari Lowongan Kerja', 'wplokerbjm'),
            'not_found'                => esc_html__('Tidak Ada Lowongan Kerja Tersedia', 'wplokerbjm'),
            'not_found_in_trash'       => esc_html__('Tidak Ada Lowongan Kerja di Tempat Sampah.', 'wplokerbjm'),
            'parent_item_colon'        => esc_html__('Parent Lowongan:', 'wplokerbjm'),
            'all_items'                => esc_html__('Semua Lowongan Kerja', 'wplokerbjm'),
            'archives'                 => esc_html__('Lowongan Archives', 'wplokerbjm'),
            'attributes'               => esc_html__('Lowongan Attributes', 'wplokerbjm'),
            'insert_into_item'         => esc_html__('Insert into lowongan', 'wplokerbjm'),
            'uploaded_to_this_item'    => esc_html__('Uploaded to this lowongan', 'wplokerbjm'),
            'featured_image'           => esc_html__('Featured image', 'wplokerbjm'),
            'set_featured_image'       => esc_html__('Set featured image', 'wplokerbjm'),
            'remove_featured_image'    => esc_html__('Remove featured image', 'wplokerbjm'),
            'use_featured_image'       => esc_html__('Use as featured image', 'wplokerbjm'),
            'menu_name'                => esc_html__('Lowongan Kerja', 'wplokerbjm'),
            'filter_items_list'        => esc_html__('Filter lowongan kerja list', 'wplokerbjm'),
            'filter_by_date'           => esc_html__('', 'wplokerbjm'),
            'items_list_navigation'    => esc_html__('Lowongan Kerja list navigation', 'wplokerbjm'),
            'items_list'               => esc_html__('Lowongan Kerja list', 'wplokerbjm'),
            'item_published'           => esc_html__('Lowongan published.', 'wplokerbjm'),
            'item_published_privately' => esc_html__('Lowongan published privately.', 'wplokerbjm'),
            'item_reverted_to_draft'   => esc_html__('Lowongan reverted to draft.', 'wplokerbjm'),
            'item_scheduled'           => esc_html__('Lowongan scheduled.', 'wplokerbjm'),
            'item_updated'             => esc_html__('Lowongan updated.', 'wplokerbjm'),
        ];
        
        $args = [
            'label'               => esc_html__('Lowongan Kerja', 'wplokerbjm'),
            'labels'              => $labels,
            'description'         => '',
            'public'              => true,
            'hierarchical'        => false,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => true,
            'query_var'           => true,
            'can_export'          => true,
            'delete_with_user'    => true,
            'has_archive'         => true,
            'rest_base'           => '',
            'show_in_menu'        => true,
            'menu_position'       => '',
            'menu_icon'           => 'dashicons-admin-users',
            'capability_type'     => 'post',
            'supports'            => ['title', 'editor', 'thumbnail'],
            'taxonomies'          => ['jenis-pekerjaan', 'lokasi-pekerjaan', 'kategori-lowongan', 'usia', 'gaji', 'pengalaman', 'pendidikan', 'gender'],
            'rewrite'             => [
                'slug'       => 'lowongan',
                'with_front' => false,
            ],
        ];

        register_post_type('lowongan', $args);
    }
}