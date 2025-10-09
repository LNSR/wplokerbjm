<?php

namespace WPLokerBJM\Views\Page;

class SingleView
{
	public function __construct(
		private \WPLokerBJM\Services\Job\JobServices $jobServices,
		private \WPLokerBJM\Services\REST\RESTData $restData,
	) {
	}

	public function getProps($post_id): array
	{

		$data = [
			'job' => $this->restData->getSingleOverlayData($post_id)
		];

		return $data;
	}


	public function render(int $post_id): void
	{
		?>
		<?= $this->jobServices->renderJobPostingJsonLd($post_id); ?>
		<div id="app">
			<script type="application/json" data-props>
				<?= wp_json_encode($this->getProps($post_id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
			</script>
		</div>
		<?php
	}
}
