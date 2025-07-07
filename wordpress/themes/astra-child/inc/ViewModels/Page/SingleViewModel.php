<?php

/**
 * ViewModel for single-lowongan.php
 */

namespace AstraChild\ViewModels\Page;

use AstraChild\Repositories\JobRepository;
use AstraChild\Factories\JobDataFactory;
use AstraChild\Services\CustomField\SocialMediaService;
use AstraChild\Services\Job\FormatterServices;
use AstraChild\Services\Job\JobServices;
use AstraChild\Resources\Components\Partial\JobSummaryRows;
use AstraChild\Resources\Components\Partial\JobsContactsRows;

class SingleViewModel
{
	public array $jobdata = [];
	public string $jobPostingJsonLd = '';

	public function __construct(
		public JobRepository $jobRepository,
		public SocialMediaService $socialMediaService,
		public JobDataFactory $jobDataFactory,
		public FormatterServices $formatterServices,
		public JobServices $JobServices
	) {
	}

	public function setJobDataInfo(int $post_id): void
	{
		$this->jobdata = $this->jobRepository->getJobData($post_id);
	}

	public function viewJobPostingJsonLd(int $post_id): string
	{
		return $this->jobPostingJsonLd = $this->JobServices->renderJobPostingJsonLd($post_id);
	}

	public function viewNamaPerusahaan(): string
	{
		$jobdata = $this->jobdata;

		// fallback to custom field if taxonomy is not set
		// * IMPORTANT : custom field 'nama_perusahaan' is deprecated 
		$namaPerusahaan = !empty($jobdata['perusahaan_taxo'])
			? $jobdata['perusahaan_taxo']
			: ($jobdata['nama_perusahaan'] ?? '');

		if (empty($namaPerusahaan)) {
			return '';
		}

		ob_start();
		?>
		<section>
			<h2 class="text-2xl flex items-center gap-2 !mb-4">
				<i class="fas fa-user-tie text-blue-500"></i>
				<span class="!font-bold"><?= esc_html($namaPerusahaan); ?></span>
			</h2>
			<div class="divider"></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function viewTentangPerusahaan(): string
	{

		$jobdata = $this->jobdata;

		if (empty($this->jobdata['tentang_perusahaan'])) {
			return '';
		}

		ob_start();
		?>
		<section>
			<h2 class="text-xl flex items-center gap-2 !mb-4">
				<i class="fas fa-map-marker-alt text-blue-600"></i>
				<span class="font-bold">Tentang Perusahaan</span>
			</h2>
			<?= $jobdata['tentang_perusahaan']; ?>
			<div class="divider"></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function viewRingkasanPekerja(): string
	{
		$jobdata = $this->jobdata;

		if (
			empty($jobdata['jenis_pekerjaan_taxo']) &&
			empty($jobdata['pendidikan_taxo']) &&
			empty($jobdata['gender_taxo']) &&
			(empty($jobdata['gaji_minimal']) || empty($jobdata['gaji_maksimal'])) &&
			(empty($jobdata['umur_min']) || empty($jobdata['umur_max'])) &&
			empty($jobdata['lokasi_taxo']) &&
			empty($jobdata['deadline'])
		) {
			return '';
		}

		$rows = JobSummaryRows::getSummaryRows($jobdata);

		ob_start();
		?>
		<section>
			<h2 class="flex items-center gap-2 !mb-4">
				<i class="fas fa-clipboard-check text-blue-600"></i>
				<span class="font-bold">Ringkasan Pekerjaan</span>
			</h2>
			<div class="gap-4 mt-4">
				<div class="gap-x-6 gap-y-4 text-lg">
					<?php foreach ($rows as $row): ?>
						<?php
						$labelClass = '';
						$labelClass = match ($row['label']) {
							'Jenis Pekerjaan' => 'sm:ml-1 ml-3',
							'Pendidikan' => 'ml-3',
							'Pengalaman' => 'ml-3',
							'Gender' => 'ml-3',
							'Usia' => 'ml-3',
							'Deadline' => 'ml-3',
							'Gaji' => 'ml-3',
							'Lokasi' => 'ml-3',
							default => 'ml-2',
						};
						?>
						<div class="flex items-start lg:space-x-2 space-x-1 mb-2">
							<i class="fas <?= $row['icon'] ?> text-blue-600 w-3 text-justify pt-2"></i>
							<span class="ml-3 !font-semibold whitespace-nowrap min-w-[120px]"><?= $row['label'] ?></span>
							<span class="<?= $labelClass ?> !font-semibold">:</span>
							<span class="!font-semibold"><?= $row['value'] ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<div class="divider"></div>
		<?php
		return ob_get_clean();
	}

	public function viewDeskripsiPekerjaan(): string
	{
		$jobdata = $this->jobdata;

		if (empty($this->jobdata['deskripsi_pekerjaan'])) {
			return '';
		}

		ob_start();
		?>
		<section>
			<h2 class="text-xl flex items-center gap-2 !mb-4">
				<i class="fas fa-info-circle text-blue-600"></i>
				<span class="font-bold">Deskripsi Pekerjaan</span>
			</h2>
			<?= $jobdata['deskripsi_pekerjaan']; ?>
			<div class="divider"></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function viewPersyaratan(): string
	{
		$jobdata = $this->jobdata;

		if (empty($this->jobdata['persyaratan'])) {
			return '';
		}

		ob_start();
		?>
		<section>
			<h2 class="text-xl flex items-center gap-2 !mb-4">
				<i class="fas fa-check-circle text-blue-600"></i>
				<span class="font-bold">Persyaratan</span>
			</h2>
			<?= $jobdata['persyaratan']; ?>
			<div class="divider"></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function viewCaraMelamar(): string
	{
		$jobdata = $this->jobdata;

		if (empty($this->jobdata['cara_melamar'])) {
			return '';
		}

		ob_start();
		?>
		<section>
			<h2 class="text-xl flex items-center gap-2 !mb-4">
				<i class="fas fa-file-signature text-blue-600"></i>
				<span class="font-bold">Cara Melamar</span>
			</h2>
			<?= $jobdata['cara_melamar']; ?>
			<div class="divider"></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function viewBenefit(): string
	{
		$jobdata = $this->jobdata;
		if (empty($this->jobdata['benefit'])) {
			return '';
		}
		ob_start();
		?>
		<section>
			<h2 class="text-xl flex items-center gap-2 !mb-4">
				<i class="fas fa-hand-holding-heart text-blue-600"></i>
				<span class="font-bold">Benefit</span>
			</h2>
			<?= $jobdata['benefit']; ?>
			<div class="divider"></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function viewContact(): string
	{
		$jobdata = $this->jobdata;

		if (
			empty(array_filter($jobdata['email_kontak'] ?? [])) &&
			empty(array_filter($jobdata['nomor_kontak'] ?? [])) &&
			empty(array_filter($jobdata['situs_kontak'] ?? []))
		) {
			return '';
		}

		ob_start();
		?>
		<section>
			<h2 class="text-xl flex items-center justify-between !mb-4">
				<span class="flex items-center gap-2">
					<i class="fas fa-address-card text-blue-600"></i>
					<span class="font-bold">Kontak</span>
				</span>
			</h2>
			<div class="grid grid-cols-1 gap-4 mt-4">
				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
					<?php
					$contacts = JobsContactsRows::getJobContactsRows($jobdata);
					foreach ($contacts as $contact): ?>
						<div class="flex items-center">
							<i class="<?= $contact['icon']; ?> text-blue-600 w-6 text-center text-xl"></i>
							<div class="ml-2 font-semibold text-md">
								<span class="block font-semibold "><?= $contact['label']; ?>:</span>
								<a href="<?= $contact['href']; ?>" target="_blank" rel="noopener noreferrer"
									class="block font-semibold break-words"><?= $contact['value']; ?></a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<div class="divider"></div>
		<?php
		return ob_get_clean();
	}

	public function viewSosmed(): string
	{
		$socialMediaItems = $this->jobDataFactory->createSocialMediaItems($this->jobdata['social_media'] ?? []);

		if (empty($socialMediaItems)) {
			return '';
		}

		ob_start();
		?>
		<section>
			<h2 class="text-xl flex items-center gap-2 !mb-4">
				<i class="fas fa-address-book text-blue-600"></i>
				<span class="font-bold">Sosial Media</span>
			</h2>
			<div class="grid grid-cols-1 gap-4 mt-4">
				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
					<?php foreach ($socialMediaItems as $item): ?>
						<div class="flex items-center">
							<i class="<?= $item['icon']; ?> text-blue-600 w-6 text-center text-xl"></i>
							<div class="ml-2 font-semibold text-md">
								<span class="block"><?= $item['platform']; ?>:</span>
								<a href="<?= $item['url']; ?>" target="_blank" rel="noopener noreferrer" class="block">
									<?= $item['platform'] === 'Whatsapp' ? FormatterServices::formatPhoneNumber($item['username']) : $item['username']; ?>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<div class="divider"></div>
		<?php
		return ob_get_clean();
	}

	public function viewFloatingActionButton(): string
	{
		return '<div id="floating-action-button"></div>';
	}

	public function viewFloatingAstraColorSwitchButton(): string
	{
		return '<div id="color-switch-button"></div>';
	}
}
