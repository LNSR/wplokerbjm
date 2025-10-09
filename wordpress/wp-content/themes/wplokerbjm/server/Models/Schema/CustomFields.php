<?php

namespace WPLokerBJM\Models\Schema;
use WPLokerBJM\Contracts\HooksInterface;

/**
 * Custom Fields Schema
 *
 * Provides schema for custom fields used in job listings.
 *
 * @note This class serves as a blueprint/template for defining Meta Box custom fields.
 *       The actual source of truth for field configurations, data, and behavior is the
 *       Meta Box GUI builder and the database storage. Code changes here may be overridden
 *       by GUI/DB settings. Use this as a reference for field structure, but manage fields
 *       primarily through the Meta Box admin interface.
 * @package WPLokerBJM\Models\Schema
 */
class CustomFields implements HooksInterface
{
    public function registerActions(): void
    {
        // No actions to register in this class
    }

    public function registerFilters(): void
    {
        add_filter('rwmb_meta_boxes', [$this, 'lowongan_meta_boxes']); // ! Removed priority to allow GUI/DB to take precedence
    }

    public function lowongan_meta_boxes($meta_boxes)
    {
        $prefix = '';

        $meta_boxes[] = [
            'title' => __('Informasi Lowongan', 'wplokerbjm'),
            'id' => 'job-listing',
            'post_types' => ['lowongan'],
            'context' => 'side',
            'closed' => false,
            'fields' => [
                [
                    'name' => __('Nama Perusahaan', 'wplokerbjm'),
                    'id' => $prefix . 'nama_perusahaan',
                    'type' => 'text',
                    'label_description' => __('Masukkan nama resmi perusahaan atau toko yang membuka lowongan.', 'wplokerbjm'),
                    'desc' => __('Contoh: PT Astra International Tbk', 'wplokerbjm'),
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                    'limit_type' => 'character',
                ],
                [
                    'name' => __('Tentang Perusahaan', 'wplokerbjm'),
                    'id' => $prefix . 'tentang_perusahaan',
                    'type' => 'wysiwyg',
                    'label_description' => __('Tuliskan profil singkat perusahaan atau informasi umum tentang perusahaan.', 'wplokerbjm'),
                    'desc' => __('Contoh: Perusahaan bergerak di bidang otomotif dan telah berdiri sejak 1970.', 'wplokerbjm'),
                    'raw' => true,
                    'required' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                    'limit_type' => 'character',
                ],
                [
                    'name' => __('Deskripsi Pekerjaan', 'wplokerbjm'),
                    'id' => $prefix . 'deskripsi_pekerjaan',
                    'type' => 'wysiwyg',
                    'label_description' => __('Jelaskan tugas, tanggung jawab, dan ruang lingkup pekerjaan yang ditawarkan.', 'wplokerbjm'),
                    'desc' => __('Contoh: Melakukan administrasi data penjualan dan membantu proses rekap laporan harian.', 'wplokerbjm'),
                    'raw' => true,
                    'required' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                    'limit_type' => 'character',
                ],
                [
                    'name' => __('Umur Minimal', 'wplokerbjm'),
                    'id' => $prefix . 'umur_min',
                    'type' => 'number',
                    'label_description' => __('Isi usia minimal pelamar jika ada batasan usia bawah.', 'wplokerbjm'),
                    'desc' => __('Kosongkan jika tidak ada batasan usia minimal.', 'wplokerbjm'),
                    'min' => 1,
                    'max' => 100,
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
                [
                    'name' => __('Umur Maksimal', 'wplokerbjm'),
                    'id' => $prefix . 'umur_max',
                    'type' => 'number',
                    'label_description' => __('Isi usia maksimal pelamar jika ada batasan usia atas.', 'wplokerbjm'),
                    'desc' => __('Kosongkan jika tidak ada batasan usia maksimal.', 'wplokerbjm'),
                    'min' => 1,
                    'max' => 100,
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
                [
                    'name' => __('Pengalaman Kerja', 'wplokerbjm'),
                    'id' => $prefix . 'pengalaman',
                    'type' => 'number',
                    'label_description' => __('Tulis jumlah tahun pengalaman kerja yang dibutuhkan.', 'wplokerbjm'),
                    'desc' => __('Contoh: 2 (untuk minimal 2 tahun pengalaman). Kosongkan jika tidak wajib.', 'wplokerbjm'),
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
                [
                    'name' => __('Persyaratan & Kualifikasi', 'wplokerbjm'),
                    'id' => $prefix . 'persyaratan',
                    'type' => 'wysiwyg',
                    'label_description' => __('Daftar persyaratan dan kualifikasi yang harus dipenuhi pelamar.', 'wplokerbjm'),
                    'desc' => __('Contoh: Minimal lulusan SMA/SMK, mampu bekerja dalam tim, jujur, dan disiplin.', 'wplokerbjm'),
                    'raw' => true,
                    'required' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                    'limit_type' => 'character',
                ],
                [
                    'name' => __('Cara Melamar', 'wplokerbjm'),
                    'id' => $prefix . 'cara_melamar',
                    'type' => 'wysiwyg',
                    'label_description' => __('Jelaskan langkah-langkah atau instruksi bagi pelamar untuk mengirimkan lamaran.', 'wplokerbjm'),
                    'desc' => __('Contoh: Kirim CV dan surat lamaran ke email perusahaan atau melalui website resmi.', 'wplokerbjm'),
                    'raw' => true,
                    'hide_from_rest' => false,
                    'limit_type' => 'character',
                ],
                [
                    'name' => __('Benefit', 'wplokerbjm'),
                    'id' => $prefix . 'benefit',
                    'type' => 'wysiwyg',
                    'label_description' => __('Sebutkan fasilitas atau keuntungan yang didapatkan jika diterima.', 'wplokerbjm'),
                    'desc' => __('Contoh: Gaji pokok, tunjangan makan, BPJS, bonus tahunan.', 'wplokerbjm'),
                    'raw' => true,
                    'hide_from_rest' => false,
                    'limit_type' => 'character',
                ],
                [
                    'name' => __('Gaji Minimal', 'wplokerbjm'),
                    'id' => $prefix . 'gaji_minimal',
                    'type' => 'number',
                    'label_description' => __('Isi nominal gaji minimal yang ditawarkan (tanpa tanda titik/koma).', 'wplokerbjm'),
                    'desc' => __('Kosongkan jika tidak ingin menampilkan gaji minimal.', 'wplokerbjm'),
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
                [
                    'name' => __('Gaji Maksimal', 'wplokerbjm'),
                    'id' => $prefix . 'gaji_maksimal',
                    'type' => 'number',
                    'label_description' => __('Isi nominal gaji maksimal yang ditawarkan (tanpa tanda titik/koma).', 'wplokerbjm'),
                    'desc' => __('Kosongkan jika tidak ingin menampilkan gaji maksimal.', 'wplokerbjm'),
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
                [
                    'name' => __('Deadline Pendaftaran', 'wplokerbjm'),
                    'id' => $prefix . 'deadline',
                    'type' => 'date',
                    'label_description' => __('Tanggal terakhir pelamar dapat mengirimkan lamaran.', 'wplokerbjm'),
                    'desc' => __('Kosongkan jika tidak ada batas waktu pendaftaran.', 'wplokerbjm'),
                    'timestamp' => false,
                    'inline' => false,
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
                [
                    'name' => __('Email Kontak', 'wplokerbjm'),
                    'id' => $prefix . 'email_kontak',
                    'type' => 'email',
                    'label_description' => __('Alamat email resmi untuk menerima lamaran atau pertanyaan.', 'wplokerbjm'),
                    'desc' => __('Bisa diisi lebih dari satu email jika diperlukan(contoh:muhammadindra003@gmail.com).', 'wplokerbjm'),
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => true,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
                [
                    'name' => __('Nomor Kontak', 'wplokerbjm'),
                    'id' => $prefix . 'nomor_kontak',
                    'type' => 'text',
                    'label_description' => __('Nomor telepon/HP perusahaan yang dapat dihubungi.', 'wplokerbjm'),
                    'desc' => __('Bisa diisi lebih dari satu nomor. Hanya angka, +, spasi, dan tanda - yang diperbolehkan. Contoh:+6283862447271', 'wplokerbjm'),
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => true,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                    'limit_type' => 'character',
                    'pattern' => '[0-9+\\s-]+',
                ],
                [
                    'name' => __('Situs Kontak', 'wplokerbjm'),
                    'id' => $prefix . 'situs_kontak',
                    'type' => 'url',
                    'label_description' => __('Alamat website resmi perusahaan.', 'wplokerbjm'),
                    'desc' => __('Bisa diisi lebih dari satu situs jika ada(contoh:https://lowongankerjabanjarmasin.com).', 'wplokerbjm'),
                    'required' => false,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => true,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
                [
                    'name' => __('Sosial Media', 'wplokerbjm'),
                    'id' => $prefix . 'social_media',
                    'type' => 'fieldset_text',
                    'label_description' => __('Masukkan username atau link sosial media perusahaan untuk masing-masing platform.', 'wplokerbjm'),
                    'desc' => __('Isi hanya username (tanpa @) atau link lengkap(contoh: loker_banjarmasin; WhatsApp perlu dimulai dengan +62 (contoh:+6283862447271).', 'wplokerbjm'),
                    'options' => [
                        'Whatsapp' => 'Whatsapp',
                        'Instagram' => 'Instagram',
                        'Facebook' => 'Facebook',
                        'X / Twitter' => 'X / Twitter',
                        'Threads' => 'Threads',
                        'Tiktok' => 'Tiktok',
                        'LinkedIn' => 'LinkedIn',
                        'Youtube' => 'Youtube',
                        'Telegram' => 'Telegram',
                    ],
                    'required' => false,
                    'clone' => true,
                    'min_clone' => 0,
                    'max_clone' => -1,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
                [
                    'name' => __('Status Pekerjaan', 'wplokerbjm'),
                    'id' => $prefix . 'status_pekerjaan',
                    'type' => 'select',
                    'label_description' => __('Tentukan status prioritas lowongan ini.', 'wplokerbjm'),
                    'desc' => __('Normal: Lowongan biasa. Urgent: Butuh segera. Pinned: Selalu tampil di atas.', 'wplokerbjm'),
                    'options' => [
                        0 => __('Normal', 'wplokerbjm'),
                        2 => __('Urgent', 'wplokerbjm'),
                        3 => __('Pinned', 'wplokerbjm'),
                    ],
                    'multiple' => false,
                    'select_all_none' => false,
                    'required' => true,
                    'disabled' => false,
                    'readonly' => false,
                    'clone' => false,
                    'clone_empty_start' => false,
                    'hide_from_rest' => false,
                ],
            ],
        ];

        return $meta_boxes;
    }
}
