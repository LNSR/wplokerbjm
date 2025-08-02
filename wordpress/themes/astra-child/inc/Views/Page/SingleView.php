<?php

namespace AstraChild\Views\Page;

class SingleView
{
	public function __construct(
		private \AstraChild\Services\Job\JobServices $jobServices,
		private \AstraChild\Services\REST\RESTData $restData
	) {
	}

	public function render(int $post_id): void
	{
		$props = [
			'job' => $this->restData->getSingleOverlayData($post_id)
		];
		?>
		<?= $this->jobServices->renderJobPostingJsonLd($post_id); ?>
		<div id="single-lowongan"
			data-props='<?= esc_attr(json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
		</div>
		<?php
	}
}
