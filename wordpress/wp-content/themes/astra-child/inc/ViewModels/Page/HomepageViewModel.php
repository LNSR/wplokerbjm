<?php

namespace AstraChild\ViewModels\Page;
use AstraChild\Components\Hero;
use AstraChild\Components\JobGrid;
use AstraChild\Components\JobCarousel;
use AstraChild\Layouts\Layouts;
use AstraChild\Services\Job\JobServices;

use AstraChild\QueryBuilders\JobQuery;

class HomepageViewModel
{

	public function __construct(
		private Hero $hero,
		private JobGrid $jobGrid,
		private JobCarousel $jobCarousel,
		private JobServices $jobServices,
		private Layouts $layouts
	) {

	}

	public function getProps(): array
	{
		return [
			'layouts' => $this->layouts->getProps(),
			'hero' => $this->hero->getProps(),
			'carousel' => $this->jobCarousel->getProps(),
			'jobGrid' => $this->jobGrid->getProps(
				JobQuery::latestJobsArgs(1, 12),
				'Lowongan Terbaru',
				'latest'
			)
		];
	}
	public function getSchema(): array
	{
		return $this->jobGrid->getSchemaCard(
			JobQuery::latestJobsArgs(1, 12)
		);
	}
}
