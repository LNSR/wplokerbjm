<?php

namespace WPLokerBJM\Services\REST;

use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Models\Schema\{Taxonomies, CustomFields};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;

class RESTData
{
    public function __construct(
        private \WPLokerBJM\Factories\JobDataFactory $jobDataFactory,
        private \WPLokerBJM\Services\Schema\JobSchemaOrg $jobSchema
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
        $cacheKey = CacheKey::REST_CARD_PREFIX . $post_id;
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
                CustomFields::NAMA_PERUSAHAAN => !empty($jobdata[Taxonomies::PERUSAHAAN])
                    ? html_entity_decode($jobdata[Taxonomies::PERUSAHAAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') // prioritize taxonomy perusahaan first
                    : (isset($jobdata[CustomFields::NAMA_PERUSAHAAN]) ? html_entity_decode($jobdata[CustomFields::NAMA_PERUSAHAAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''),
                'ringkasanPekerjaan' => [
                    Taxonomies::JENIS_PEKERJAAN => $jobdata[Taxonomies::JENIS_PEKERJAAN] ?? null,
                    Taxonomies::PENDIDIKAN => $jobdata[Taxonomies::PENDIDIKAN] ?? null,
                    Taxonomies::GENDER => $jobdata[Taxonomies::GENDER] ?? null,
                    Taxonomies::LOKASI_PEKERJAAN => $jobdata[Taxonomies::LOKASI_PEKERJAAN] ?? null,
                    CustomFields::PENGALAMAN => $jobdata[CustomFields::PENGALAMAN] ?? null,
                    CustomFields::GAJI_MINIMAL => $jobdata[CustomFields::GAJI_MINIMAL] ?? null,
                    CustomFields::GAJI_MAKSIMAL => $jobdata[CustomFields::GAJI_MAKSIMAL] ?? null,
                    CustomFields::UMUR_MIN => $jobdata[CustomFields::UMUR_MIN] ?? null,
                    CustomFields::UMUR_MAX => $jobdata[CustomFields::UMUR_MAX] ?? null,
                ],
                CustomFields::DEADLINE => $jobdata[CustomFields::DEADLINE] ?? null,
                CustomFields::STATUS_PEKERJAAN => $jobdata[CustomFields::STATUS_PEKERJAAN] ?? null,
                'permalink' => esc_url(get_permalink($post_id)),
                'post_time' => get_post_time('c', false, $post_id),
            ];

            $data = SharedUtils::filterEmptyValues($data);

            Cache::set($cacheKey, $data, 86400); // Cache for 1 day
            return $data;
        } catch (\Exception $e) {
            Logger::error('REST', 'RESTData::getCardData error for post ' . $post_id . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get detailed data for a single job overlay
     * Also used for SingleView props
     * @param int $post_id
     * @return array
     */
    public function getJobDetailData(int $post_id): array
    {
        $cacheKey = CacheKey::REST_JOBDETAIL_PREFIX . $post_id . (is_user_logged_in() ? '_logged_in' : '_public');
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $jobdata = $this->jobDataFactory->createJobData($post_id);

            $data = [
                'id' => $post_id,
                'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                CustomFields::NAMA_PERUSAHAAN => !empty($jobdata[Taxonomies::PERUSAHAAN])
                    ? html_entity_decode($jobdata[Taxonomies::PERUSAHAAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') // prioritize taxonomy perusahaan first
                    : (isset($jobdata[CustomFields::NAMA_PERUSAHAAN]) ? html_entity_decode($jobdata[CustomFields::NAMA_PERUSAHAAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null),
                CustomFields::TENTANG_PERUSAHAAN => isset($jobdata[CustomFields::TENTANG_PERUSAHAAN]) ? html_entity_decode($jobdata[CustomFields::TENTANG_PERUSAHAAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null,
                'ringkasanPekerjaan' => [
                    Taxonomies::JENIS_PEKERJAAN => $jobdata[Taxonomies::JENIS_PEKERJAAN] ?? null,
                    Taxonomies::PENDIDIKAN => $jobdata[Taxonomies::PENDIDIKAN] ?? null,
                    Taxonomies::GENDER => $jobdata[Taxonomies::GENDER] ?? null,
                    Taxonomies::LOKASI_PEKERJAAN => $jobdata[Taxonomies::LOKASI_PEKERJAAN] ?? null,
                    CustomFields::PENGALAMAN => $jobdata[CustomFields::PENGALAMAN] ?? null,
                    CustomFields::GAJI_MINIMAL => $jobdata[CustomFields::GAJI_MINIMAL] ?? null,
                    CustomFields::GAJI_MAKSIMAL => $jobdata[CustomFields::GAJI_MAKSIMAL] ?? null,
                    CustomFields::UMUR_MIN => $jobdata[CustomFields::UMUR_MIN] ?? null,
                    CustomFields::UMUR_MAX => $jobdata[CustomFields::UMUR_MAX] ?? null,
                    CustomFields::DEADLINE => $jobdata[CustomFields::DEADLINE] ?? null,
                ],
                CustomFields::DESKRIPSI_PEKERJAAN => isset($jobdata[CustomFields::DESKRIPSI_PEKERJAAN]) ? html_entity_decode($jobdata[CustomFields::DESKRIPSI_PEKERJAAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null,
                CustomFields::PERSYARATAN => isset($jobdata[CustomFields::PERSYARATAN]) ? html_entity_decode($jobdata[CustomFields::PERSYARATAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null,
                CustomFields::CARA_MELAMAR => isset($jobdata[CustomFields::CARA_MELAMAR]) ? html_entity_decode($jobdata[CustomFields::CARA_MELAMAR], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null,
                CustomFields::BENEFIT => isset($jobdata[CustomFields::BENEFIT]) ? html_entity_decode($jobdata[CustomFields::BENEFIT], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null,
                'contacts' => [
                    CustomFields::EMAIL_KONTAK => $jobdata[CustomFields::EMAIL_KONTAK] ?? null,
                    CustomFields::NOMOR_KONTAK => $jobdata[CustomFields::NOMOR_KONTAK] ?? null,
                    CustomFields::SITUS_KONTAK => $jobdata[CustomFields::SITUS_KONTAK] ?? null,
                ],
                CustomFields::SOCIAL_MEDIA => $jobdata[CustomFields::SOCIAL_MEDIA] ?? null,
                'post_time' => get_post_time('c', false, $post_id),
            ];

            if (is_user_logged_in()) {
                $data['duplicateNonce'] = self::pluginSpecificNonce('duplicatePost', $post_id);
            }

            $data = SharedUtils::filterEmptyValues($data);

            Cache::set($cacheKey, $data, 86400); // Cache for 1 day
            return $data;
        } catch (\Exception $e) {
            Logger::error('REST', 'RESTData::getSingleOverlayData error for post ' . $post_id . ': ' . $e->getMessage());
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

    /**
     * Get JobSchema data for REST responses
     * !Useful in future for headless setups
     * @return array
     */
    public function JobSchema(int $post_id) {
        return $this->jobSchema->getJobPostingSchema($post_id);
    }
}
