<?php

/**
 * ViewModel for single-lowongan.php
 */

namespace AstraChild\ViewModels\Page;

use AstraChild\Repositories\JobRepository;
use AstraChild\Factories\JobDataFactory;
use AstraChild\Services\CustomField\SocialMediaService;
use AstraChild\Services\Job\FormatterServices;
use AstraChild\Resources\Components\FloatingActionButton;
use AstraChild\Resources\Components\ColorSwitchButton;
use AstraChild\Resources\Components\Partial\JobSummaryRows;
use AstraChild\Resources\Components\Partial\JobsContactsRows;

class SingleViewModel
{
	public array $jobdata = [];

	public function __construct(
		protected JobRepository $jobRepository,
		protected SocialMediaService $socialMediaService,
		protected JobDataFactory $jobDataFactory
	) {}

	public function setJobDataInfo(int $post_id): void
	{
		$this->jobdata = $this->jobRepository->getJobData($post_id);
	}

	public function viewNamaPerusahaan(): string
	{

		$jobdata = $this->jobdata;

		if (empty($this->jobdata['nama_perusahaan'])) {
			return '';
		}

		ob_start();
?>
		<section>
			<h2 class="text-2xl flex items-center gap-2 !mb-4">
				<i class="fas fa-user-tie text-blue-500"></i>
				<span class="!font-bold"><?= $jobdata['nama_perusahaan']; ?></span>
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
			<div class="grid grid-cols-1 gap-4 mt-4">
				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 text-lg">
					<?php foreach ($rows as $row): ?>
						<div class="flex items-center">
							<i class="fas <?= $row['icon'] ?> text-blue-600 w-6 text-center"></i>
							<span class="ml-2 !font-semibold"><?= $row['label'] ?>:</span>
							<span class="ml-2 !font-semibold"><?= $row['value'] ?></span>
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
					foreach ($contacts as $contact) : ?>
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
		return FloatingActionButton::render();
	}

	public function viewFloatingAstraColorSwitchButton(): string
	{
		return ColorSwitchButton::render();
	}
}
