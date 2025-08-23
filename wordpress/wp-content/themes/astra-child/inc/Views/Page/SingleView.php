<?php

namespace AstraChild\Views\Page;
use AstraChild\Components\Placeholder;

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
		return [
			'layouts' => $this->layouts->getProps(),
			'job' => $this->restData->getSingleOverlayData($post_id)
		];
	}


	public function render(int $post_id): void
	{
		?>
		<?= $this->jobServices->renderJobPostingJsonLd($post_id); ?>
		<div id="single-lowongan">
			<script type="application/json" data-props>
				<?= wp_json_encode($this->getProps($post_id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
			</script>

			<?php
			$theme_dir = get_theme_file_path('');
			$skeleton = $theme_dir . '/assets/dist/skeletons/single-lowongan.html';

			if (file_exists($skeleton)) {
				echo file_get_contents($skeleton);
			} else {
				echo Placeholder::render();
			}
			?>
		</div>
		<?php
	}
}
