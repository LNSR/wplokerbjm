<?php

namespace AstraChild\Models;

/**
 * Entity class for custom fields data
 */
class CustomFieldEntity
{
    public function __construct(
        public $nama_perusahaan = null,
        public $tentang_perusahaan = null,
        public $deskripsi_pekerjaan = null,
        public $umur_min = null,
        public $umur_max = null,
        public $pengalaman = null,
        public $persyaratan = null,
        public $cara_melamar = null,
        public $benefit = null,
        public $gaji_minimal = null,
        public $gaji_maksimal = null,
        public $deadline = null,
        public $email_kontak = null,
        public $nomor_kontak = null,
        public $situs_kontak = null,
        public $social_media = null,
        public $status_pekerjaan = null
    ) {}
}
