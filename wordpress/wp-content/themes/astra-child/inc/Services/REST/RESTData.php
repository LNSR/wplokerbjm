<?php

namespace AstraChild\Services\REST;

use AstraChild\Core\Cache;

class RESTData
{
    public function __construct(
        public \AstraChild\Factories\JobDataFactory $jobDataFactory
    ) {
    }

    public function getCardData(int $post_id): array
    {
        try {
            $cacheKey = 'card_data_' . $post_id;

            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $jobdata = $this->jobDataFactory->buatDataPekerjaan($post_id);

            $data = [
                'slug' => get_post_field('post_name', $post_id),
                'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'nama_perusahaan' => !empty($jobdata['perusahaan_taxo'])
                    ? html_entity_decode($jobdata['perusahaan_taxo'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    : (isset($jobdata['nama_perusahaan']) ? html_entity_decode($jobdata['nama_perusahaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''),
                'ringkasanPekerjaan' => [
                    'jenis_pekerjaan_taxo' => $jobdata['jenis_pekerjaan_taxo'] ?? null,
                    'pendidikan_taxo' => $jobdata['pendidikan_taxo'] ?? null,
                    'pengalaman' => $jobdata['pengalaman'] ?? null,
                    'gender_taxo' => $jobdata['gender_taxo'] ?? null,
                    'gaji_minimal' => $jobdata['gaji_minimal'] ?? null,
                    'gaji_maksimal' => $jobdata['gaji_maksimal'] ?? null,
                    'umur_min' => $jobdata['umur_min'] ?? null,
                    'umur_max' => $jobdata['umur_max'] ?? null,
                    'lokasi_taxo' => $jobdata['lokasi_taxo'] ?? null,
                ],
                'deadline' => $jobdata['deadline'] ?? null,
                'statusjob' => $jobdata['status_pekerjaan'] ?? null,
                'permalink' => esc_url(get_permalink($post_id)),
                'post_time' => get_post_time('c', false, $post_id),
            ];

            Cache::set($cacheKey, $data, 86400); // Cache for 24 hours

            return $data;
        } catch (\Exception $e) {
            error_log('RESTData::getCardData error for post ' . $post_id . ': ' . $e->getMessage());
            return [];
        }
    }

    public function getSingleOverlayData(int $post_id): array
    {
        try {
            $cacheKey = 'single_overlay_data_' . $post_id;

            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $jobdata = $this->jobDataFactory->buatDataPekerjaan($post_id);

            $data = [
                'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'namaPerusahaan' => !empty($jobdata['perusahaan_taxo'])
                    ? html_entity_decode($jobdata['perusahaan_taxo'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    : (isset($jobdata['nama_perusahaan']) ? html_entity_decode($jobdata['nama_perusahaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''),
                'tentangPerusahaan' => isset($jobdata['tentang_perusahaan']) ? html_entity_decode($jobdata['tentang_perusahaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
                'ringkasanPekerjaan' => [
                    'jenis_pekerjaan_taxo' => $jobdata['jenis_pekerjaan_taxo'] ?? null,
                    'pendidikan_taxo' => $jobdata['pendidikan_taxo'] ?? null,
                    'pengalaman' => $jobdata['pengalaman'] ?? null,
                    'gender_taxo' => $jobdata['gender_taxo'] ?? null,
                    'gaji_minimal' => $jobdata['gaji_minimal'] ?? null,
                    'gaji_maksimal' => $jobdata['gaji_maksimal'] ?? null,
                    'umur_min' => $jobdata['umur_min'] ?? null,
                    'umur_max' => $jobdata['umur_max'] ?? null,
                    'lokasi_taxo' => $jobdata['lokasi_taxo'] ?? null,
                    'deadline' => $jobdata['deadline'] ?? null,
                ],
                'deskripsiPekerjaan' => isset($jobdata['deskripsi_pekerjaan']) ? html_entity_decode($jobdata['deskripsi_pekerjaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
                'persyaratan' => isset($jobdata['persyaratan']) ? html_entity_decode($jobdata['persyaratan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
                'caraMelamar' => isset($jobdata['cara_melamar']) ? html_entity_decode($jobdata['cara_melamar'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
                'benefit' => isset($jobdata['benefit']) ? html_entity_decode($jobdata['benefit'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
                'contacts' => [
                    'email_kontak' => $jobdata['email_kontak'] ?? [],
                    'nomor_kontak' => $jobdata['nomor_kontak'] ?? [],
                    'situs_kontak' => $jobdata['situs_kontak'] ?? [],
                ],
                'social_media' => $jobdata['social_media'] ?? [],
                'post_time' => get_post_time('c', false, $post_id),
            ];
            
            if (is_user_logged_in()) {
                $data['id'] = $post_id;
            }

            Cache::set($cacheKey, $data, 86400); // Cache for 24 hours

            return $data;
        } catch (\Exception $e) {
            error_log('RESTData::getSingleOverlayData error for post ' . $post_id . ': ' . $e->getMessage());
            return [];
        }
    }
}
