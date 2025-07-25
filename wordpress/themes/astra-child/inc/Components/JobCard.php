<?php

namespace AstraChild\Components;

use AstraChild\Core\Container;
use AstraChild\Repositories\JobRepository;
use AstraChild\Components\Partial\JobSummaryRows;
use AstraChild\Services\Job\FormatterServices;

class JobCard
{

	public function __construct(
		private JobRepository $jobRepository,
	) {
	}

	/**
	 * Render a job card.
	 * @param int $post_id
	 * @param string $variant
	 */
	public function render(int $post_id, string $variant = ''): string
	{
		$jobdata = $this->jobRepository->getJobData($post_id);
		$permalink = esc_url(get_permalink($post_id));

		ob_start();

		switch ($variant) {
			case 'carousel':
				?>
				<article
					class="block group rounded-xl transition-all duration-300 cursor-pointer carousel-card max-w-full border-2 border-blue-400 shadow-md hover:shadow-lg hover:border-blue-600 hover:border-solid">
					<a href="<?= $permalink; ?>" class="contents">
						<div class="card-body relative p-3 gap-0 flex flex-col min-h-[300px] h-full">
							<?= self::renderCardContent($post_id, $jobdata); ?>
						</div>
					</a>
				</article>
				<?php
				break;
			case 'featured':
				?>
				<article
					class="block group rounded-xl transition-all duration-300 cursor-pointer w-full max-w border-2 border-blue-400 shadow-lg hover:shadow-xl hover:border-blue-600 hover:scale-[1.02] hover:border-solid">
					<a href="<?= $permalink; ?>" class="contents">
						<div class="card-body relative p-4 gap-1 flex flex-col h-full">
							<?= self::renderCardContent($post_id, $jobdata); ?>
						</div>
					</a>
				</article>
				<?php
				break;
		}

		return ob_get_clean();
	}

	public static function renderCardContent(int $post_id, array $jobdata)
	{
		$statusjob = self::render_statusjob($jobdata);
		$deadline = self::render_deadline($jobdata);
		$has_status = !empty(trim($statusjob));
		$has_deadline = !empty(trim($deadline));

		$rows = JobSummaryRows::getSummaryRows($jobdata);

		ob_start();
		?>
		<div class="flex-1 flex flex-col justify-start">
			<div class="flex items-center justify-between mb-2 gap-x-2">
				<h3 class="card-title text-lg md:text-xl !font-bold group-hover:text-blue-700 transition-colors">
					<?= esc_html(get_the_title($post_id)); ?>
				</h3>
				<time class="text-lg !font-semibold text-center gap-2" datetime="<?= esc_attr(get_post_time('c', false, $post_id)); ?>">
					<?= esc_html(FormatterServices::formatTimeAgo(get_post_time('U', false, $post_id))); ?>
				</time>
			</div>
			<?php
			// fallback to custom field if taxonomy is not set
			// * IMPORTANT : custom field 'nama_perusahaan' is used for non-company
			$namaPerusahaan = !empty($jobdata['perusahaan_taxo'])
				? $jobdata['perusahaan_taxo']
				: ($jobdata['nama_perusahaan'] ?? '');
			?>
			<?php if (empty($namaPerusahaan)): ?>
				<div class="divider mt-0"></div>
			<?php endif; ?>
			<?php if (!empty($namaPerusahaan)): ?>
				<h4 class="!font-bold flex items-center gap-2 !mb-6">
					<i class="fas fa-user-tie !text-[var(--ast-global-color-1)]"></i>
					<?= esc_html($namaPerusahaan); ?>
				</h4>
				<div class="divider !-mt-4"></div>
			<?php endif; ?>
			<div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
				<?php foreach ($rows as $row): ?>
					<?php if ($row['label'] === 'Deadline')
						continue; ?>
					<span class="flex items-center text-base md:text-base font-semibold gap-2 py-1">
						<i class="fas <?= $row['icon'] ?> text-[var(--ast-global-color-1)]"></i>
						<?= $row['value']; ?>
					</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php if ($has_status || $has_deadline): ?>
			<div class="divider my-2"></div>
		<?php endif; ?>
		<div class="flex items-center justify-between font-semibold">
			<?= $statusjob; ?>
			<?= $deadline; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_statusjob(array $jobdata): string
	{
		$status = (int) $jobdata['status_pekerjaan'];

		[$label, $color] = match ($status) {
			2 => ['Urgent', 'border border-red-500 text-red-500 bg-transparent text-md'],
			3 => ['Pinned', 'border border-yellow-500 text-yellow-700 bg-transparent text-md'],
			default => ['', ''],
		};

		if (!$label)
			return '';

		ob_start();
		?>
		<span class="inline-block px-3 py-1 text-xs <?= esc_attr($color); ?>">
			<?= esc_html($label); ?>
		</span>
		<?php
		return ob_get_clean();
	}

	public static function render_deadline(array $jobdata): string
	{
		if (empty($jobdata['deadline'])) {
			return '';
		}

		$deadline = new \DateTime($jobdata['deadline']);
		$now = new \DateTime('now');
		$deadline->setTime(0, 0, 0);
		$now->setTime(0, 0, 0);

		$interval = $now->diff($deadline);
		$days_left = (int) $interval->format('%r%a');

		[$text, $icon_color] = match (true) {
			$days_left > 1 => ["Tersisa {$days_left} hari", 'border border-blue-500 text-blue-500 bg-transparent'],
			$days_left === 1 => ["Tersisa 1 hari", 'border border-yellow-500 text-yellow-700 bg-transparent'],
			$days_left === 0 => ["Hari terakhir", 'border border-red-500 text-red-500 bg-transparent'],
			$days_left === -1 => ["Berakhir kemarin", 'border border-red-500 text-red-500 bg-transparent'],
			$days_left < -1 => ["Berakhir " . abs($days_left) . " hari lalu", 'border border-red-500 text-red-500 bg-transparent'],
			default => ["Berakhir hari ini", 'border border-red-500 text-red-500 bg-transparent'],
		};

		if (!$text)
			return '';

		ob_start();
		?>
		<div class="flex items-center <?= $icon_color; ?> p-2">
			<i class="fas fa-calendar-alt mr-2"></i>
			<span class="text-sm"><?= $text; ?></span>
		</div>
		<?php
		return ob_get_clean();
	}
}