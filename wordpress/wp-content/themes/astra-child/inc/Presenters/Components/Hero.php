<?php

namespace AstraChild\Presenters\Components;

class Hero
{

    public function getProps(): array
    {
        $current_search = isset($_GET['cari']) ? sanitize_text_field($_GET['cari']) : '';
        $current_lokasi = isset($_GET['lokasi']) ? sanitize_text_field($_GET['lokasi']) : '';
        $current_gender = isset($_GET['gender']) ? sanitize_text_field($_GET['gender']) : '';
        $current_pendidikan = isset($_GET['pendidikan']) ? sanitize_text_field($_GET['pendidikan']) : '';
        $current_sort = (isset($_GET['sort']) && $_GET['sort'] === 'asc')
            ? ['value' => 'asc', 'label' => 'Terlama']
            : ['value' => 'desc', 'label' => 'Terbaru'];

        return [
            'currentSearch' => $current_search,
            'currentLokasi' => !empty($current_lokasi) ? (is_array($current_lokasi) ? $current_lokasi : [$current_lokasi]) : [],
            'currentGender' => !empty($current_gender) ? (is_array($current_gender) ? $current_gender : [$current_gender]) : [],
            'currentPendidikan' => !empty($current_pendidikan) ? (is_array($current_pendidikan) ? $current_pendidikan : [$current_pendidikan]) : [],
            'currentSort' => $current_sort,
            'archiveLink' => esc_url(get_post_type_archive_link('lowongan'))
        ];
    }
}