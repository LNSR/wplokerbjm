<?php

namespace AstraChild\Models\Schema;
use AstraChild\Contracts\HooksInterface;

/**
 * Custom Fields Schema
 * 
 * Provides schema for custom fields
 */
class CustomFields implements HooksInterface
{
    /**
     * Register custom fields
     * 
     * @return void
     */
    public function registerActions(): void
    {
        add_filter('rwmb_meta_boxes', [$this, 'lowongan_meta_boxes']);
    }

    public function registerFilters(): void
    {
        // No filters to register in this class
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
                    'label_description' => __('Masukkan nama resmi perusahaan atau toko yang membuka lowongan.', 'astra-child'),
                    'desc'              => __('Contoh: PT Astra International Tbk', 'astra-child'),
                    'required'          => false,
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
                    'label_description' => __('Tuliskan profil singkat perusahaan atau informasi umum tentang perusahaan.', 'astra-child'),
                    'desc'              => __('Contoh: Perusahaan bergerak di bidang otomotif dan telah berdiri sejak 1970.', 'astra-child'),
                    'raw'               => true,
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
                    'label_description' => __('Jelaskan tugas, tanggung jawab, dan ruang lingkup pekerjaan yang ditawarkan.', 'astra-child'),
                    'desc'              => __('Contoh: Melakukan administrasi data penjualan dan membantu proses rekap laporan harian.', 'astra-child'),
                    'raw'               => true,
                    'required'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                ],
                [
                    'name'              => __('Umur Minimal', 'astra-child'),
                    'id'                => $prefix . 'umur_min',
                    'type'              => 'number',
                    'label_description' => __('Isi usia minimal pelamar jika ada batasan usia bawah.', 'astra-child'),
                    'desc'              => __('Kosongkan jika tidak ada batasan usia minimal.', 'astra-child'),
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
                    'label_description' => __('Isi usia maksimal pelamar jika ada batasan usia atas.', 'astra-child'),
                    'desc'              => __('Kosongkan jika tidak ada batasan usia maksimal.', 'astra-child'),
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
                    'label_description' => __('Tulis jumlah tahun pengalaman kerja yang dibutuhkan.', 'astra-child'),
                    'desc'              => __('Contoh: 2 (untuk minimal 2 tahun pengalaman). Kosongkan jika tidak wajib.', 'astra-child'),
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
                    'label_description' => __('Daftar persyaratan dan kualifikasi yang harus dipenuhi pelamar.', 'astra-child'),
                    'desc'              => __('Contoh: Minimal lulusan SMA/SMK, mampu bekerja dalam tim, jujur, dan disiplin.', 'astra-child'),
                    'raw'               => true,
                    'required'          => false,
                    'clone'             => false,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                ],
                [
                    'name'              => __('Cara Melamar', 'astra-child'),
                    'id'                => $prefix . 'cara_melamar',
                    'type'              => 'wysiwyg',
                    'label_description' => __('Jelaskan langkah-langkah atau instruksi bagi pelamar untuk mengirimkan lamaran.', 'astra-child'),
                    'desc'              => __('Contoh: Kirim CV dan surat lamaran ke email perusahaan atau melalui website resmi.', 'astra-child'),
                    'raw'               => true,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                ],
                [
                    'name'              => __('Benefit', 'astra-child'),
                    'id'                => $prefix . 'benefit',
                    'type'              => 'wysiwyg',
                    'label_description' => __('Sebutkan fasilitas atau keuntungan yang didapatkan jika diterima.', 'astra-child'),
                    'desc'              => __('Contoh: Gaji pokok, tunjangan makan, BPJS, bonus tahunan.', 'astra-child'),
                    'raw'               => true,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                ],
                [
                    'name'              => __('Gaji Minimal', 'astra-child'),
                    'id'                => $prefix . 'gaji_minimal',
                    'type'              => 'number',
                    'label_description' => __('Isi nominal gaji minimal yang ditawarkan (tanpa tanda titik/koma).', 'astra-child'),
                    'desc'              => __('Kosongkan jika tidak ingin menampilkan gaji minimal.', 'astra-child'),
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
                    'label_description' => __('Isi nominal gaji maksimal yang ditawarkan (tanpa tanda titik/koma).', 'astra-child'),
                    'desc'              => __('Kosongkan jika tidak ingin menampilkan gaji maksimal.', 'astra-child'),
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
                    'label_description' => __('Tanggal terakhir pelamar dapat mengirimkan lamaran.', 'astra-child'),
                    'desc'              => __('Kosongkan jika tidak ada batas waktu pendaftaran.', 'astra-child'),
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
                    'label_description' => __('Alamat email resmi untuk menerima lamaran atau pertanyaan.', 'astra-child'),
                    'desc'              => __('Bisa diisi lebih dari satu email jika diperlukan(contoh:muhammadindra003@gmail.com).', 'astra-child'),
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => true,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Nomor Kontak', 'astra-child'),
                    'id'                => $prefix . 'nomor_kontak',
                    'type'              => 'text',
                    'label_description' => __('Nomor telepon/HP perusahaan yang dapat dihubungi.', 'astra-child'),
                    'desc'              => __('Bisa diisi lebih dari satu nomor. Hanya angka, +, spasi, dan tanda - yang diperbolehkan. Contoh:+6283862447271', 'astra-child'),
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => true,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                    'limit_type'        => 'character',
                    'pattern'           => '[0-9+\\s-]+',
                ],
                [
                    'name'              => __('Situs Kontak', 'astra-child'),
                    'id'                => $prefix . 'situs_kontak',
                    'type'              => 'url',
                    'label_description' => __('Alamat website resmi perusahaan.', 'astra-child'),
                    'desc'              => __('Bisa diisi lebih dari satu situs jika ada(contoh:https://lowongankerjabanjarmasin.com).', 'astra-child'),
                    'required'          => false,
                    'disabled'          => false,
                    'readonly'          => false,
                    'clone'             => true,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Sosial Media', 'astra-child'),
                    'id'                => $prefix . 'social_media',
                    'type'              => 'fieldset_text',
                    'label_description' => __('Masukkan username atau link sosial media perusahaan untuk masing-masing platform.', 'astra-child'),
                    'desc'              => __('Isi hanya username (tanpa @) atau link lengkap(contoh: loker_banjarmasin; WhatsApp perlu dimulai dengan +62 (contoh:+6283862447271).', 'astra-child'),
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
                    'clone'             => true,
                    'min_clone'         => 0,
                    'max_clone'         => -1,
                    'clone_empty_start' => false,
                    'hide_from_rest'    => false,
                ],
                [
                    'name'              => __('Status Pekerjaan', 'astra-child'),
                    'id'                => $prefix . 'status_pekerjaan',
                    'type'              => 'select',
                    'label_description' => __('Tentukan status prioritas lowongan ini.', 'astra-child'),
                    'desc'              => __('Normal: Lowongan biasa. Urgent: Butuh segera. Pinned: Selalu tampil di atas.', 'astra-child'),
                    'options'           => [
                        0 => __('Normal', 'astra-child'),
                        2 => __('Urgent', 'astra-child'),
                        3 => __('Pinned', 'astra-child'),
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
