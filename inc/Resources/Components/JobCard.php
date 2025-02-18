<?php

namespace AstraChild\Resources\Components;

use AstraChild\Core\Container;
use AstraChild\Repositories\JobRepository;
use AstraChild\Resources\Components\Partial\JobSummaryRows;

class JobCard
{
	/**
	 * Render a job card.
	 * @param int $post_id
	 * @param string $variant
	 */
	public static function render(int $post_id, string $variant = ''): string
	{
		/** @var JobRepository $repo */
		$repo = Container::getContainer()->get(JobRepository::class);
		$jobdata = $repo->getJobData($post_id);
		$permalink = esc_url(get_permalink($post_id));

		ob_start();

		switch ($variant) {
			case 'carousel':
?>
				<a href="<?= $permalink; ?>"
					class="block group rounded-xl transition-all duration-300 cursor-pointer carousel-card max-w-md border-2 border-blue-400 shadow-md hover:shadow-lg hover:border-blue-600 hover:border-solid mx-3">
					<div class="card-body relative p-3 gap-0 flex flex-col h-full">
						<?= self::renderCardContent($post_id, $jobdata); ?>
					</div>
				</a>
			<?php
				break;
			case 'featured':
			?>
				<a href="<?= $permalink; ?>"
					class="block group rounded-xl transition-all duration-300 cursor-pointer w-full max-w border-2 border-blue-400 shadow-lg hover:shadow-xl hover:border-blue-600 hover:scale-[1.02] hover:border-solid">
					<div class="card-body relative p-4 gap-1 flex flex-col h-full">
						<?= self::renderCardContent($post_id, $jobdata); ?>
					</div>
				</a>
		<?php
				break;
		}

		return ob_get_clean();
	}

	private static function renderCardContent(int $post_id, array $jobdata)
	{
		$statusjob = self::render_statusjob($post_id, $jobdata);
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
				<span class="text-lg text-center gap-2">
					<?= esc_html(self::render_post_time($post_id)); ?>
				</span>
			</div>
			<?php if (empty($jobdata['nama_perusahaan'])) : ?>
				<div class="divider -mt-2"></div>
			<?php endif; ?>
			<?php if (!empty($jobdata['nama_perusahaan'])) : ?>
				<h4 class="!font-bold flex items-center gap-2 !mb-6">
					<i class="fas fa-user-tie text-blue-600"></i>
					<?= $jobdata['nama_perusahaan']; ?>
				</h4>
				<div class="divider !-mt-6"></div>
			<?php endif; ?>
			<div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
				<?php foreach ($rows as $row): ?>
					<?php if ($row['label'] === 'Deadline') continue; ?>
					<span class="flex items-center text-base md:text-base gap-2 py-1">
						<i class="fas <?= $row['icon'] ?> text-blue-600"></i>
						<?= $row['value']; ?>
					</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php if ($has_status || $has_deadline) : ?>
			<div class="divider my-2"></div>
		<?php endif; ?>
		<div class="flex items-center justify-between">
			<?= $statusjob; ?>
			<?= $deadline; ?>
		</div>
	<?php
		return ob_get_clean();
	}

	private static function render_statusjob(int $post_id, array $jobdata): string
	{
		$status = (int) $jobdata['status_pekerjaan'];

		[$label, $color] = match ($status) {
			2 => ['Urgent', 'border border-red-500 text-red-500 bg-transparent text-md'],
			3 => ['Pinned', 'border border-yellow-500 text-yellow-700 bg-transparent text-md'],
			default => ['', ''],
		};

		if (!$label) return '';

		ob_start();
	?>
		<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold <?= esc_attr($color); ?>">
			<?= esc_html($label); ?>
		</span>
	<?php
		return ob_get_clean();
	}

	private static function render_deadline(array $jobdata): string
	{
		if (empty($jobdata['deadline'])) {
			return '';
		}
		$deadline_ts = strtotime($jobdata['deadline']);
		$now = time();
		$days_left = floor(($deadline_ts - $now) / 86400);

		[$text, $icon_color] = match (true) {
			$days_left > 1   => ["Tersisa {$days_left} hari", 'border border-blue-500 text-blue-500 bg-transparent'],
			$days_left === 1 => ["Hari terakhir", 'border border-yellow-500 text-yellow-700 bg-transparent'],
			$days_left === 0 => ["Hari terakhir", 'border border-red-500 text-red-500 bg-transparent'],
			$days_left === -1 => ["Berakhir kemarin", 'border border-red-500 text-red-500 bg-transparent'],
			$days_left < -1  => ["Berakhir " . abs($days_left) . " hari lalu", 'border border-red-500 text-red-500 bg-transparent'],
			default          => ["Berakhir hari ini", 'border border-red-500 text-red-500 bg-transparent'],
		};

		if (!$text)
			return '';

		ob_start();
	?>
		<div class="flex items-center <?= $icon_color; ?> rounded-md p-2">
			<i class="fas fa-calendar-alt mr-2"></i>
			<span class="text-sm"><?= $text; ?></span>
		</div>
<?php
		return ob_get_clean();
	}

	private static function render_post_time(int $post_id): string
	{
		$post_time = get_post_time('U', true, $post_id);
		$time_diff = human_time_diff($post_time, current_time('timestamp'));

		// Translate English time units to Indonesian
		$translations = [
			'minute' => 'menit',
			'minutes' => 'menit',
			'hour'   => 'jam',
			'hours'  => 'jam',
			'day'    => 'hari',
			'days'   => 'hari',
			'week'   => 'minggu',
			'weeks'  => 'minggu',
			'month'  => 'bulan',
			'months' => 'bulan',
			'year'   => 'tahun',
			'years'  => 'tahun',
			'second' => 'detik',
			'seconds' => 'detik',
		];

		$time_diff = strtr($time_diff, $translations);

		return sprintf('Sekitar %s lalu', $time_diff);
	}
}
