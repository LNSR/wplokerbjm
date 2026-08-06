<?php

namespace WPLokerBJM\Services\GraphQL;

use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Models\Schema\{Taxonomies, CustomFields};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Factories\JobDataFactory;
use WPLokerBJM\Core\Theme\ThemeProp;
use WPLokerBJM\Services\Schema\JobSchemaOrg;

/**
 * @phpstan-type RingkasanPekerjaan array{
 *     jenis_pekerjaan?: string|null,
 *     pendidikan?: string|null,
 *     gender?: string|null,
 *     lokasi_pekerjaan?: string|null,
 *     pengalaman?: int|null,
 *     gaji_minimal?: int|null,
 *     gaji_maksimal?: int|null,
 *     umur_min?: int|null,
 *     umur_max?: int|null,
 *     deadline?: string|null,
 * }
 * @phpstan-type CardData array{
 *     id: int,
 *     slug: string,
 *     title: string,
 *     nama_perusahaan?: string,
 *     ringkasanPekerjaan: RingkasanPekerjaan,
 *     status_pekerjaan?: int,
 *     permalink: string,
 *     post_time?: string,
 * }
 * @phpstan-type JobDetailData array{
 *     id: int,
 *     slug: string,
 *     permalink: string,
 *     title: string,
 *     nama_perusahaan?: string,
 *     tentang_perusahaan?: string|null,
 *     ringkasanPekerjaan: RingkasanPekerjaan,
 *     deskripsi_pekerjaan?: string|null,
 *     persyaratan?: string|null,
 *     cara_melamar?: string|null,
 *     benefit?: string|null,
 *     contacts?: array{email_kontak?: string, nomor_kontak?: string, situs_kontak?: string},
 *     social_media?: string|null,
 *     dpNonce?: string,
 *     post_time?: string,
 * }
 * @phpstan-import-type ThemeData from ThemeProp
 * @phpstan-import-type JobData from JobDataFactory
 * @phpstan-import-type JobPostingSchema from JobSchemaOrg
 * @phpstan-import-type ItemListSchema from JobSchemaOrg
 */
class GraphQLData
{
    public function __construct(
        private JobDataFactory $jobDataFactory,
        private JobSchemaOrg $jobSchema,
        private ThemeProp $ThemeProp
    ) {
    }

    /**
     * Get card data for a Homepage Jobcard listing
     * used for JobGrid and JobCarousel props
     * @param int $post_id Post ID to fetch card data for
     * @return CardData Processed card data
     */
    public function getCardData(int $post_id): array
    {
        $post_id = (int) $post_id; // Explicit coercion for type safety
        $cacheKey = CacheKey::GRAPHQL_JOB_CARD_PREFIX . $post_id;
        /** @var CardData|false $cached */
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
                'ringkasanPekerjaan' => $this->getRingkasanPekerjaan($jobdata),
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
     * @param int $post_id Post ID to fetch detailed data for
     * @return JobDetailData Processed job detail data
     */
    public function getJobDetailData(int $post_id): array
    {
        $post_id = (int) $post_id; // Explicit coercion for type safety
        // Use per-user cache for logged-in users to avoid leaking user-specific nonces
        $cacheKey = is_user_logged_in()
            ? CacheKey::GRAPHQL_JOB_DETAIL_PREFIX . $post_id . '_user_' . (int) get_current_user_id()
            : CacheKey::GRAPHQL_JOB_DETAIL_PREFIX . $post_id . '_public';


        $noncePlugin = static fn(string $action, int $postId): string => wp_create_nonce($action . '_' . $postId);

        /** @var JobDetailData|false $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            if (is_user_logged_in()) {
                $cached['dpNonce'] = $noncePlugin('duplicate_post_new_draft', $post_id);
                return $cached;
            } else {
                // safety remove in case cached from logged-in
                if (isset($cached['dpNonce'])) {
                    unset($cached['dpNonce']);
                }
                return $cached;
            }
        }

        try {
            $jobdata = $this->jobDataFactory->createJobData($post_id);

            $data = [
                'id' => $post_id,
                'slug' => get_post_field('post_name', $post_id),
                'permalink' => esc_url(get_permalink($post_id)),
                'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                CustomFields::NAMA_PERUSAHAAN => !empty($jobdata[Taxonomies::PERUSAHAAN])
                    ? html_entity_decode($jobdata[Taxonomies::PERUSAHAAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') // prioritize taxonomy perusahaan first
                    : (isset($jobdata[CustomFields::NAMA_PERUSAHAAN]) ? html_entity_decode($jobdata[CustomFields::NAMA_PERUSAHAAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null),
                CustomFields::TENTANG_PERUSAHAAN => isset($jobdata[CustomFields::TENTANG_PERUSAHAAN]) ? html_entity_decode($jobdata[CustomFields::TENTANG_PERUSAHAAN], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null,
                'ringkasanPekerjaan' => $this->getRingkasanPekerjaan($jobdata),
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


            if (is_user_logged_in())
                $data['dpNonce'] = $noncePlugin('duplicate_post_new_draft', $post_id);

            $data = SharedUtils::filterEmptyValues($data);

            Cache::set($cacheKey, $data, 86400); // Cache for 1 day
            return $data;
        } catch (\Exception $e) {
            Logger::error('REST', 'RESTData::getSingleOverlayData error for post ' . $post_id . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract job summary fields from full job data array.
     *
     * @param JobData $jobdata Full job data from JobDataFactory::createJobData
     * @return RingkasanPekerjaan
     */
    private function getRingkasanPekerjaan(array $jobdata): array
    {
        return [
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
        ];
    }

    /**
     * Get theme data for REST/GraphQL responses
     * Useful in future for headless setups
     * @return ThemeData
     */
    public function getThemeData(): array
    {
        return $this->ThemeProp->themeData();
    }

    /**
     * Get Schema.org JobPosting JSON-LD data for a single job.
     * Useful in future for headless setups
     * @param int $post_id Post ID
     * @return JobPostingSchema
     */
    public function JobSchema(int $post_id): array
    {
        $post_id = (int) $post_id; // Explicit coercion for type safety
        return $this->jobSchema->getJobPostingSchema($post_id);
    }

    /**
     * Return an ItemList schema id for post IDs
     * @param array<int> $post_ids
     * @return ItemListSchema
     */
    public function ItemListJobPostings(array $post_ids): array
    {
        return $this->jobSchema->getItemListSchema($post_ids);
    }
}
