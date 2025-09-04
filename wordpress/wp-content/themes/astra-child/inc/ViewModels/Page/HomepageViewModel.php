<?php

namespace AstraChild\ViewModels\Page;
use AstraChild\Components\Hero;
use AstraChild\Components\JobGrid;
use AstraChild\Components\JobCarousel;
use AstraChild\Layouts\Layouts;
use AstraChild\Services\Job\JobServices;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Core\Cache;

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
		$cache_key = 'page_homepage_props';
		$cached = Cache::get($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		$props = [
			'layouts' => $this->layouts->getProps(),
			'hero' => $this->hero->getProps(),
			'carousel' => $this->jobCarousel->getProps(),
			'jobGrid' => $this->jobGrid->getProps(
				JobQuery::latestJobsArgs(1, 12),
				'Lowongan Terbaru',
				'latest'
			)
		];

		Cache::set($cache_key, $props, 86400); // Cache for 1 day
		return $props;
	}
	public function getSchema(): array
	{
		return $this->jobGrid->getSchemaCard(
			JobQuery::latestJobsArgs(1, 12)
		);
	}
}
