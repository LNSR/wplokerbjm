<?php

namespace WPLokerBJM\Views\Page;
use WPLokerBJM\Presenters\DocumentHTML;
class SingleView
{
	
	public function __construct(
		private \WPLokerBJM\Services\Job\JobServices $jobServices,
		private \WPLokerBJM\Services\REST\RESTData $restData,
	) {
	}

	public function render(): void
	{
		$post_id = get_the_ID();
		$props = [
			'job' => $this->restData->getSingleOverlayData($post_id)
		];

		$schema = $this->jobServices->renderJobPostingJsonLd($post_id);
		DocumentHTML::renderHead($schema);
		?>
		<div id="app">
			<script type="application/json" data-props>
				<?= wp_json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
			</script>
		</div>
		<?php DocumentHTML::renderFooter();
	}
}
