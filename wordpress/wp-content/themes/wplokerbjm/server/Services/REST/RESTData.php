<?php

namespace WPLokerBJM\Services\REST;

use WPLokerBJM\Core\Cache;

class RESTData
{
    public const CARD_CACHE_PREFIX = 'rest_card_';
    public const OVERLAY_CACHE_PREFIX = 'rest_overlay_';
    public const CACHE_TTL = 86400; // 1 day

    public function __construct(
        public \WPLokerBJM\Factories\JobDataFactory $jobDataFactory
    ) {
    }

    /**
     * Get card data for a Homepage Jobcard listing
     * used for JobGrid and JobCarousel props
     * @param int $post_id
     * @return array
     */
    public function getCardData(int $post_id): array
    {
        $cacheKey = self::CARD_CACHE_PREFIX . $post_id;
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $jobdata = $this->jobDataFactory->createJobData($post_id);

            $data = [
                'id' => $post_id,
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

            Cache::set($cacheKey, $data, self::CACHE_TTL);
            return $data;
        } catch (\Exception $e) {
            error_log('RESTData::getCardData error for post ' . $post_id . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get detailed data for a single job overlay
     * Also used for SingleView props
     * @param int $post_id
     * @return array
     */
    public function getSingleOverlayData(int $post_id): array
    {
        $cacheKey = self::OVERLAY_CACHE_PREFIX . $post_id . (is_user_logged_in() ? '_logged_in' : '_public');
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $jobdata = $this->jobDataFactory->createJobData($post_id);

            $data = [
                'id' => $post_id,
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
                $data['duplicateNonce'] = self::pluginSpecificNonce('duplicatePost', $post_id);
            }

            Cache::set($cacheKey, $data, self::CACHE_TTL);
            return $data;
        } catch (\Exception $e) {
            error_log('RESTData::getSingleOverlayData error for post ' . $post_id . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Provides plugin-specific nonce for actions
     * @param string $action
     * @param int $post_id
     * @return string
     */
    private static function pluginSpecificNonce(string $action, int $post_id): string
    {
        return match ($action) {
            'duplicatePost' => wp_create_nonce('dt-duplicate-page-' . $post_id),
            default => '',
        };
    }

    /**
     * Get theme data for REST responses
     * !Useful in future for headless setups
     * @return array
     */
    public function getThemeData()
    {
        return \WPLokerBJM\Core\Hooks\Theme\ThemeInject::themeData();
    }
}
