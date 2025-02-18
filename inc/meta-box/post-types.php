<?php
// custom post type using Meta Box
add_action('init', 'register_lowongan_kerja');
function register_lowongan_kerja()
{
    $labels = [
    'name'                     => esc_html__('Lowongan Kerja', 'astra-child'),
    'singular_name'            => esc_html__('Lowongan', 'astra-child'),
    'add_new'                  => esc_html__('Tambah Baru', 'astra-child'),
    'add_new_item'             => esc_html__('Tambah Lowongan Baru', 'astra-child'),
    'edit_item'                => esc_html__('Edit Lowongan', 'astra-child'),
    'new_item'                 => esc_html__('Lowongan Baru', 'astra-child'),
    'view_item'                => esc_html__('Lihat Lowongan', 'astra-child'),
    'view_items'               => esc_html__('Lihat Lowongan Kerja', 'astra-child'),
    'search_items'             => esc_html__('Cari Lowongan Kerja', 'astra-child'),
    'not_found'                => esc_html__('Tidak Ada Lowongan Kerja Tersedia', 'astra-child'),
    'not_found_in_trash'       => esc_html__('Tidak Ada Lowongan Kerja di Tempat Sampah.', 'astra-child'),
    'parent_item_colon'        => esc_html__('Parent Lowongan:', 'astra-child'),
    'all_items'                => esc_html__('Semua Lowongan Kerja', 'astra-child'),
    'archives'                 => esc_html__('Lowongan Archives', 'astra-child'),
    'attributes'               => esc_html__('Lowongan Attributes', 'astra-child'),
    'insert_into_item'         => esc_html__('Insert into lowongan', 'astra-child'),
    'uploaded_to_this_item'    => esc_html__('Uploaded to this lowongan', 'astra-child'),
    'featured_image'           => esc_html__('Featured image', 'astra-child'),
    'set_featured_image'       => esc_html__('Set featured image', 'astra-child'),
    'remove_featured_image'    => esc_html__('Remove featured image', 'astra-child'),
    'use_featured_image'       => esc_html__('Use as featured image', 'astra-child'),
    'menu_name'                => esc_html__('Lowongan Kerja', 'astra-child'),
    'filter_items_list'        => esc_html__('Filter lowongan kerja list', 'astra-child'),
    'filter_by_date'           => esc_html__('', 'astra-child'),
    'items_list_navigation'    => esc_html__('Lowongan Kerja list navigation', 'astra-child'),
    'items_list'               => esc_html__('Lowongan Kerja list', 'astra-child'),
    'item_published'           => esc_html__('Lowongan published.', 'astra-child'),
    'item_published_privately' => esc_html__('Lowongan published privately.', 'astra-child'),
    'item_reverted_to_draft'   => esc_html__('Lowongan reverted to draft.', 'astra-child'),
    'item_scheduled'           => esc_html__('Lowongan scheduled.', 'astra-child'),
    'item_updated'             => esc_html__('Lowongan updated.', 'astra-child'),
    'text_domain'              => esc_html__('astra-child', 'astra-child'),
    ];
    $args = [
    'label'               => esc_html__('Lowongan Kerja', 'astra-child'),
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
    '_slug_changed'       => true,
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
?>
