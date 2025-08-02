<?php

namespace AstraChild\Services\REST;
use AstraChild\Services\Job\FormatterServices;
use AstraChild\Components\Partial\JobSummaryRows;
use AstraChild\Components\Partial\JobsContactsRows;
use AstraChild\Components\JobCard;

class RESTData
{
    public function __construct(
        public \AstraChild\Repositories\JobRepository $jobRepository,
        public \AstraChild\Factories\JobDataFactory $jobDataFactory
    ) {}

    public function getCardData(int $post_id): array
    {
        $jobdata = $this->jobRepository->getJobData($post_id);

        return [
            'slug' => get_post_field('post_name', $post_id),
            'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'nama_perusahaan' => !empty($jobdata['perusahaan_taxo'])
                ? html_entity_decode($jobdata['perusahaan_taxo'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : (isset($jobdata['nama_perusahaan']) ? html_entity_decode($jobdata['nama_perusahaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''),
            'time_ago' => FormatterServices::formatTimeAgo(get_post_time('U', false, $post_id)),
            'summary_rows' => JobSummaryRows::getSummaryRows($jobdata),
            'statusjob' => JobCard::render_statusjob($jobdata),
            'deadline' => JobCard::render_deadline($jobdata),
            'permalink' => esc_url(get_permalink($post_id)),
            'post_time' => get_post_time('c', false, $post_id),
        ];
    }

    public function getSingleOverlayData(int $post_id): array
    {
        $jobdata = $this->jobRepository->getJobData($post_id);

        return [
            'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'namaPerusahaan' => !empty($jobdata['perusahaan_taxo'])
                ? html_entity_decode($jobdata['perusahaan_taxo'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : (isset($jobdata['nama_perusahaan']) ? html_entity_decode($jobdata['nama_perusahaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''),
            'tentangPerusahaan' => isset($jobdata['tentang_perusahaan']) ? html_entity_decode($jobdata['tentang_perusahaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'ringkasanPekerjaan' => JobSummaryRows::getSummaryRows($jobdata),
            'deskripsiPekerjaan' => isset($jobdata['deskripsi_pekerjaan']) ? html_entity_decode($jobdata['deskripsi_pekerjaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'persyaratan' => isset($jobdata['persyaratan']) ? html_entity_decode($jobdata['persyaratan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'caraMelamar' => isset($jobdata['cara_melamar']) ? html_entity_decode($jobdata['cara_melamar'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'benefit' => isset($jobdata['benefit']) ? html_entity_decode($jobdata['benefit'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'contacts' => JobsContactsRows::getJobContactsRows($jobdata),
            'social_media' => $this->jobDataFactory->createSocialMediaItems($jobdata['social_media'] ?? []),
            'post_time' => get_post_time('c', false, $post_id),
        ];
    }
}
