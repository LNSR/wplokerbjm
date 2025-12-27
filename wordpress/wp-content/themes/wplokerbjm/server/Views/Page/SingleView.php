<?php

namespace WPLokerBJM\Views\Page;
use WPLokerBJM\Presenters\DocumentHTML;
class SingleView
{
	
	public function __construct(
		private \WPLokerBJM\Services\Job\JobSchemaOrg $jobServices,
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

		DocumentHTML::renderDocument($schema, $props);
	}
}
