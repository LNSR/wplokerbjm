<?php

namespace AstraChild\Views\Page;

class SingleView
{
	public function __construct(
		private \AstraChild\Repositories\JobRepository $jobRepository,
		private \AstraChild\Factories\JobDataFactory $jobDataFactory,
		private \AstraChild\Services\Job\JobServices $jobServices
	) {
	}

	public function render(int $post_id): void
	{
		$jobdata = $this->jobRepository->getJobData($post_id);

		$props = [
			'job' => [
				'title' => get_the_title($post_id),
				'namaPerusahaan' => !empty($jobdata['perusahaan_taxo']) ? $jobdata['perusahaan_taxo'] : ($jobdata['nama_perusahaan']),
				'tentangPerusahaan' => $jobdata['tentang_perusahaan'] ?? '',
				'ringkasanPekerjaan' => \AstraChild\Components\Partial\JobSummaryRows::getSummaryRows($jobdata) ,
				'deskripsiPekerjaan' => $jobdata['deskripsi_pekerjaan'] ?? '',
				'persyaratan' => $jobdata['persyaratan'] ?? '',
				'caraMelamar' => $jobdata['cara_melamar'] ?? '',
				'benefit' => $jobdata['benefit'] ?? '',
				'contacts' => \AstraChild\Components\Partial\JobsContactsRows::getJobContactsRows($jobdata),
				'social_media' => $this->jobDataFactory->createSocialMediaItems($jobdata['social_media'] ?? []),
				'post_time' => get_post_time('c', false, $post_id),
			]
		];
		?>
		<?= $this->jobServices->renderJobPostingJsonLd($post_id); ?>
		<div id="single-lowongan"
			data-props='<?= esc_attr(json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
		</div>
		<?php
	}
}
