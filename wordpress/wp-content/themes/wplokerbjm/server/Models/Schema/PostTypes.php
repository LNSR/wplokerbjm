<?php
namespace WPLokerBJM\Models\Schema;

use DI\Attribute\Injectable;
use WPLokerBJM\Core\Container\Attributes\Action;

/**
 * Post Types Schema
 *
 * Defines custom post types for the application.
 *
 * @note This class serves as a blueprint/template for defining Meta Box post types.
 *       The actual source of truth for field configurations, data, and behavior is the
 *       Meta Box GUI builder and the database storage. Code changes here may be overridden
 *       by GUI/DB settings. Use this as a reference for field structure, but manage fields
 *       primarily through the Meta Box admin interface.
 *
 * @package WPLokerBJM\Models\Schema
 */
class PostTypes {
    
    public const POST_TYPE_LOWONGAN = 'lowongan';

    /**
     * Register the Lowongan post type
     * 
     * @return void
     */
    #[Action('init')]
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
            'has_archive'         => false,
            'rest_base'           => '',
            'show_in_menu'        => true,
            'menu_position'       => '',
            'menu_icon'           => 'dashicons-admin-users',
            'capability_type'     => 'post',
            'supports'            => ['title', 'editor', 'thumbnail'],
            'taxonomies'          => [Taxonomies::JENIS_PEKERJAAN, Taxonomies::LOKASI_PEKERJAAN, Taxonomies::KATEGORI_LOWONGAN, Taxonomies::PENDIDIKAN, Taxonomies::GENDER],
            'rewrite'             => [
                'slug'       => self::POST_TYPE_LOWONGAN,
                'with_front' => false,
            ],
        ];

        register_post_type(self::POST_TYPE_LOWONGAN, $args);
    }
}