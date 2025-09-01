<?php

namespace AstraChild\Views\Page;
use AstraChild\Components\Placeholder;
use AstraChild\Core\Cache;

class SingleView
{
	public function __construct(
		private \AstraChild\Services\Job\JobServices $jobServices,
		private \AstraChild\Services\REST\RESTData $restData,
		private \AstraChild\Layouts\Layouts $layouts,
	) {
	}

	public function getProps($post_id): array
	{
		$cacheKey = 'single_view_props_' . $post_id;

		$cached = Cache::get($cacheKey);
		if ($cached !== false) {
			return $cached;
		}

		$data = [
			'layouts' => $this->layouts->getProps(),
			'job' => $this->restData->getSingleOverlayData($post_id)
		];

		Cache::set($cacheKey, $data, 86400); // Cache for 24 hours

		return $data;
	}


	public function render(int $post_id): void
	{
		?>
		<?= $this->jobServices->renderJobPostingJsonLd($post_id); ?>
		<div id="single-lowongan">
			<script type="application/json" data-props>
				<?= wp_json_encode($this->getProps($post_id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
			</script>
			<?= Placeholder::render(); ?>
		</div>
		<?php
	}
}
