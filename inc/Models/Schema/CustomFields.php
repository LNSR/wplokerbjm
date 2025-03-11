<?php

namespace AstraChild\Models\Schema;

/**
 * Custom Fields Schema
 * 
 * Provides schema for custom fields
 */
class CustomFields
{
    /**
     * Register custom fields
     * 
     * @return void
     */
    public function register(): void
    {
        // Hook into Meta Box filter
        add_filter('rwmb_meta_boxes', [$this, 'lowongan_meta_boxes']);
    }

    /**
     * Register custom fields for the job listings
     * 
     * @param array $meta_boxes Existing meta boxes
     * @return array Updated meta boxes
     */

    public function lowongan_meta_boxes($meta_boxes)
    {
        $prefix = '';

        $meta_boxes[] = [
            'title'      => __('Informasi Lowongan', 'astra-child'),
            'id'         => 'job-listing',
            'post_types' => ['lowongan'],
            'context'    => 'side',
            'closed'     => false,
            'fields'     => [
                [
                    'name'              => __('Nama Perusahaan', 'astra-child'),
                    'id'                => $prefix . 'nama_perusahaan',
                    'type'              => 'text',
                    'label_description' => __('Toko atau Perusahaan', 'astra-child'),
                    'required'          => true,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                ],
                [
                    'name'              => __('Tentang Perusahaan', 'astra-child'),
                    'id'                => $prefix . 'tentang_perusahaan',
                    'type'              => 'wysiwyg',
                    'label_description' => __('Profil perusahaan atau tentang lowongan perusahaan yang dibuka', 'astra-child'),
                    'raw'               => false,
                    'required'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                ],
                [
                    'name'              => __('Deskripsi Pekerjaan', 'astra-child'),
                    'id'                => $prefix . 'deskripsi_pekerjaan',
                    'type'              => 'wysiwyg',
                    'label_description' => __('Menjelaskan hal yang berhubungan dengan job desk yang dibuka, S&K maupun beenfitnya', 'astra-child'),
                    'raw'               => false,
                    'required'          => true,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                ],
                [
                    'name'              => __('Umur Minimal', 'astra-child'),
                    'id'                => $prefix . 'umur_min',
                    'type'              => 'number',
                    'label_description' => __('Boleh cuma mengisi Min tanpa Max', 'astra-child'),
                    'min'               => 1,
                    'max'               => 100,
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Umur Maksimal', 'astra-child'),
                    'id'                => $prefix . 'umur_max',
                    'type'              => 'number',
                    'label_description' => __('Boleh cuma mengisi Max tanpa Min', 'astra-child'),
                    'min'               => 1,
                    'max'               => 100,
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Pengalaman Kerja', 'astra-child'),
                    'id'                => $prefix . 'pengalaman',
                    'type'              => 'number',
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Persyaratan & Kualifikasi', 'astra-child'),
                    'id'                => $prefix . 'persyaratan',
                    'type'              => 'wysiwyg',
                    'raw'               => false,
                    'required'          => true,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                ],
                [
                    'name'              => __('Gaji Minimal', 'astra-child'),
                    'id'                => $prefix . 'gaji_minimal',
                    'type'              => 'number',
                    'label_description' => __('Boleh cuma mengisi Min tanpa Max', 'astra-child'),
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Gaji Maksimal', 'astra-child'),
                    'id'                => $prefix . 'gaji_maksimal',
                    'type'              => 'number',
                    'label_description' => __('Boleh cuma mengisi Max tanpa Min', 'astra-child'),
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Deadline Pendaftaran', 'astra-child'),
                    'id'                => $prefix . 'deadline',
                    'type'              => 'date',
                    'timestamp'         => false,
                    'inline'            => false,
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Email Kontak', 'astra-child'),
                    'id'                => $prefix . 'email_kontak',
                    'type'              => 'email',
                    'label_description' => __('Email perusahaan /toko', 'astra-child'),
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Nomor Kontak', 'astra-child'),
                    'id'                => $prefix . 'nomor_kontak',
                    'type'              => 'text',
                    'label_description' => __('Nomor Perusahaan', 'astra-child'),
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                    'pattern'           => '[0-9+\\s-]+',
                ],
                [
                    'name'              => __('Situs Kontak', 'astra-child'),
                    'id'                => $prefix . 'situs_kontak',
                    'type'              => 'url',
                    'label_description' => __('Situs perusahaan', 'astra-child'),
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Sosial Media', 'astra-child'),
                    'id'                => $prefix . 'social_media',
                    'type'              => 'fieldset_text',
                    'label_description' => __('Sosmed perusahaan', 'astra-child'),
                    'options'           => [
                        'Whatsapp'    => 'Whatsapp',
                        'Instagram'   => 'Instagram',
                        'Facebook'    => 'Facebook',
                        'X / Twitter' => 'X / Twitter',
                        'Threads'     => 'Threads',
                        'Tiktok'      => 'Tiktok',
                        'LinkedIn'    => 'LinkedIn',
                        'Youtube'     => 'Youtube',
                        'Telegram'    => 'Telegram',
                    ],
                    'required'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Status Pekerjaan', 'astra-child'),
                    'id'                => $prefix . 'status_pekerjaan',
                    'type'              => 'select',
                    'label_description' => __('Prioritas pekerjaan yang akan ditampilkan di front page', 'astra-child'),
                    'options'           => [
                        0 => __('Normal', 'astra-child'),
                        2 => __('Urgent', 'astra-child'),
                        3 => __('Pinned', 'astra-child'),
                        4 => __('Pinned & Urgent', 'astra-child'),
                    ],
                    'multiple'          => false,
                    'select_all_none'   => false,
                    'required'          => true,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
            ],
        ];

        return $meta_boxes;
    }
}
