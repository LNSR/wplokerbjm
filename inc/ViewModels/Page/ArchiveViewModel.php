<?php

namespace AstraChild\ViewModels\Page;

class ArchiveViewModel
{

	public function __construct() {}

	public function viewHero()
	{
		return \AstraChild\Resources\Components\Hero::render();
	}

	public function viewSearchResults()
	{
		$params = [
			'cari'      => $_GET['cari'] ?? '',
			'lokasi'    => $_GET['lokasi'] ?? '',
			'gender'    => $_GET['gender'] ?? '',
			'pendidikan' => $_GET['pendidikan'] ?? '',
			'sort'      => $_GET['sort'] ?? 'desc',
		];
		$paged = max(1, (int) ($_GET['paged'] ?? 1));
		$query_args = \AstraChild\QueryBuilders\JobQuery::searchJobsArgs($params, $paged, 9);
		$jobs_query = new \WP_Query($query_args);
		$total_jobs = $jobs_query->found_posts;
		wp_reset_postdata();

		return \AstraChild\Resources\Components\JobGrid::render(
			$query_args,
			'Hasil Pencarian',
			'search',
			$total_jobs
		);
	}

	public function viewFloatingActionButton(): string
	{
		return \AstraChild\Resources\Components\FloatingActionButton::render();
	}

	public function viewFloatingAstraColorSwitchButton(): string
	{
		return \AstraChild\Resources\Components\ColorSwitchButton::render();
	}
}
