<?php

namespace AstraChild\Presenters\Page;
use AstraChild\Presenters\Components\Hero;
use AstraChild\Presenters\Components\JobGrid;
use AstraChild\Presenters\Components\JobCarousel;
use AstraChild\Services\Job\JobServices;

use AstraChild\QueryBuilders\JobQuery;

class HomepagePresenter
{

	public function __construct(
		private Hero $hero,
		private JobGrid $jobGrid,
		private JobCarousel $jobCarousel,
		private JobServices $jobServices
	) {

	}

	public function getProps(): array
	{

		$props = [
			'logo' => get_custom_logo(),
			'hero' => $this->hero->getProps(),
			'carousel' => $this->jobCarousel->getProps(),
			'jobGrid' => $this->jobGrid->getProps(
				JobQuery::latestJobsArgs(1, 12),
				'Lowongan Terbaru',
				'latest'
			)
		];

	return $props;
	}
	public function getSchema(): array
	{
		return $this->jobGrid->getSchemaCard(
			JobQuery::latestJobsArgs(1, 12)
		);
	}
}