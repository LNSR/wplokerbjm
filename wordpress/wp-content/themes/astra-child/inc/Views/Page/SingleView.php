<?php

namespace AstraChild\Views\Page;
use AstraChild\Presenters\Components\Placeholder;

class SingleView
{
	public function __construct(
		private \AstraChild\Services\Job\JobServices $jobServices,
		private \AstraChild\Services\REST\RESTData $restData,
	) {
	}

	public function getProps($post_id): array
	{

		$data = [
			'logo' => get_custom_logo(),
			'job' => $this->restData->getSingleOverlayData($post_id)
		];

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
