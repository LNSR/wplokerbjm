<?php

namespace AstraChild\Models;

class TaxonomyEntity
{
    public function __construct(
        public $perusahaan_taxo = null,
        public $kategori_lowongan_taxo = null,
        public $lokasi_taxo = null,
        public $jenis_pekerjaan_taxo = null,
        public $gender_taxo = null,
        public $pendidikan_taxo = null,
    ) {}
}
