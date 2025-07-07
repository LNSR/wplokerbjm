<?php

namespace AstraChild\Services\REST;
use AstraChild\Repositories\JobRepository;
use AstraChild\Services\Job\FormatterServices;
use AstraChild\Resources\Components\Partial\JobSummaryRows;
use AstraChild\Resources\Components\Partial\JobsContactsRows;
use AstraChild\Factories\JobDataFactory;

class RESTData
{
    public function __construct(
        public JobRepository $jobRepository,
        public JobDataFactory $jobDataFactory
    ) {}

    public function getCardData(int $post_id): array
    {
        $jobdata = $this->jobRepository->getJobData($post_id);

        return [
            'id' => $post_id,
            'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'nama_perusahaan' => !empty($jobdata['perusahaan_taxo'])
                ? html_entity_decode($jobdata['perusahaan_taxo'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : (isset($jobdata['nama_perusahaan']) ? html_entity_decode($jobdata['nama_perusahaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''),
            'time_ago' => FormatterServices::formatTimeAgo(get_post_time('U', false, $post_id)),
            'summary_rows' => JobSummaryRows::getSummaryRows($jobdata),
            'statusjob' => \AstraChild\Resources\Components\JobCard::render_statusjob($jobdata),
            'deadline' => \AstraChild\Resources\Components\JobCard::render_deadline($jobdata),
            'permalink' => esc_url(get_permalink($post_id)),
            'post_time' => get_post_time('c', false, $post_id),
        ];
    }

    public function getSingleOverlayData(int $post_id): array
    {
        $jobdata = $this->jobRepository->getJobData($post_id);

        $summaryRows = JobSummaryRows::getSummaryRows($jobdata);
        $contactRows = JobsContactsRows::getJobContactsRows($jobdata);

        $gaji_min = $jobdata['gaji_minimal'] ?? null;
        $gaji_max = $jobdata['gaji_maksimal'] ?? null;
        $gaji_min = (is_numeric($gaji_min) && $gaji_min !== '') ? (int)$gaji_min : null;
        $gaji_max = (is_numeric($gaji_max) && $gaji_max !== '') ? (int)$gaji_max : null;
        $formattedSalary = FormatterServices::formatSalary($gaji_min, $gaji_max);

        $umur_min = $jobdata['umur_min'] ?? null;
        $umur_max = $jobdata['umur_max'] ?? null;
        $umur_min = (is_numeric($umur_min) && $umur_min !== '') ? (int)$umur_min : null;
        $umur_max = (is_numeric($umur_max) && $umur_max !== '') ? (int)$umur_max : null;
        $formattedAge = FormatterServices::formatAge($umur_min, $umur_max);

        $sosmedRows = [];
        $formattedSosmed = [];
        if (
            !empty($jobdata['social_media']) &&
            isset($this->jobDataFactory) &&
            is_object($this->jobDataFactory) &&
            method_exists($this->jobDataFactory, 'createSocialMediaItems')
        ) {
            $sosmedRows = $this->jobDataFactory->createSocialMediaItems($jobdata['social_media']);
            $formattedSosmed = $sosmedRows;
        }

        return [
            'id' => $post_id,
            'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'jobdata' => $jobdata,
            'namaPerusahaan' => isset($jobdata['perusahaan_taxo'])
                ? html_entity_decode($jobdata['perusahaan_taxo'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : (isset($jobdata['nama_perusahaan']) ? html_entity_decode($jobdata['nama_perusahaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''),
            'tentangPerusahaan' => isset($jobdata['tentang_perusahaan']) ? html_entity_decode($jobdata['tentang_perusahaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'deskripsiPekerjaan' => isset($jobdata['deskripsi_pekerjaan']) ? html_entity_decode($jobdata['deskripsi_pekerjaan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'persyaratan' => isset($jobdata['persyaratan']) ? html_entity_decode($jobdata['persyaratan'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'caraMelamar' => isset($jobdata['cara_melamar']) ? html_entity_decode($jobdata['cara_melamar'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'benefit' => isset($jobdata['benefit']) ? html_entity_decode($jobdata['benefit'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'contact' => [
                'email' => $jobdata['email_kontak'] ?? [],
                'phone' => array_map(
                    fn($n) => FormatterServices::formatPhoneNumber($n),
                    $jobdata['nomor_kontak'] ?? []
                ),
                'website' => $jobdata['situs_kontak'] ?? [],
            ],
            'salaryFormatted' => $formattedSalary,
            'ageFormatted' => $formattedAge,
            'social_media' => $formattedSosmed,
            'summaryRows' => $summaryRows,
            'contactRows' => $contactRows,
            'sosmedRows' => $sosmedRows,
            'permalink' => esc_url(get_permalink($post_id)),
        ];
    }
}
