<?php

namespace AstraChild\Components;

use AstraChild\Core\Container;
use AstraChild\Repositories\TaxonomyRepository;

class Hero {

    public function __construct(
        private TaxonomyRepository $taxonomyRepository
    ) {
    }

    public function render() {
        $terms = $this->taxonomyRepository->getTaxonomyTerms();

        $current_search = isset($_GET['cari']) ? sanitize_text_field($_GET['cari']) : '';
        $current_lokasi = isset($_GET['lokasi']) ? sanitize_text_field($_GET['lokasi']) : '';
        $current_gender = isset($_GET['gender']) ? sanitize_text_field($_GET['gender']) : '';
        $current_pendidikan = isset($_GET['pendidikan']) ? sanitize_text_field($_GET['pendidikan']) : '';
        $current_sort = (isset($_GET['sort']) && $_GET['sort'] === 'asc')
            ? ['value' => 'asc', 'label' => 'Terlama']
            : ['value' => 'desc', 'label' => 'Terbaru'];

        $vue_props = [
            'currentSearch' => $current_search,
            'currentLokasi' => !empty($current_lokasi) ? (is_array($current_lokasi) ? $current_lokasi : [$current_lokasi]) : [],
            'currentGender' => !empty($current_gender) ? (is_array($current_gender) ? $current_gender : [$current_gender]) : [],
            'currentPendidikan' => !empty($current_pendidikan) ? (is_array($current_pendidikan) ? $current_pendidikan : [$current_pendidikan]) : [],
            'currentSort' => $current_sort,
            'archiveLink' => esc_url(get_post_type_archive_link('lowongan'))
        ];

        ob_start();
        ?>
        <section class="mx-auto px-4 py-8 text-center">
            <h1 class="text-3xl md:text-5xl !font-bold !mb-2">Temukan Lowongan Kerja Terbaru di Banjarmasin</h1>
            <p class="mb-8 text-lg !text-semibold">Update setiap hari, mudah diakses, dan gratis!</p>
            <div class="border-2 border-blue-500 rounded-xl p-4 md:p-6 min-h-[220px] sm:min-h-[306px] md:min-h-[204px]">
                <div id="search-form" data-props="<?= esc_attr(json_encode($vue_props)) ?>"></div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
