<?php

namespace AstraChild\ViewModels\Page;
use AstraChild\Components\Hero;
use AstraChild\Components\JobGrid;
use AstraChild\Layouts\Layouts;
use AstraChild\QueryBuilders\JobQuery;

class ArchiveViewModel
{

	public function __construct(
		private Hero $hero,
		private JobGrid $jobGrid,
		private JobQuery $jobQuery,
		private Layouts $layouts
	) {
	}

	/**
	 * Build props for frontend hydration for archive / search pages.
	 * Returns an array similar to HomepageViewModel::getProps()
	 */
	public function viewSearchResultsProps(): array
	{
		$params = [
			'cari' => $_GET['cari'] ?? '',
			'lokasi' => $_GET['lokasi'] ?? '',
			'gender' => $_GET['gender'] ?? '',
			'pendidikan' => $_GET['pendidikan'] ?? '',
			'sort' => $_GET['sort'] ?? 'desc',
		];

		$paged = max(1, (int) ($_GET['paged'] ?? 1));

		$query_args = JobQuery::searchJobsArgs($params, $paged, 36);

		// get total jobs for pagination info
		$jobs_query = new \WP_Query($query_args);
		$total_jobs = $jobs_query->found_posts;
		wp_reset_postdata();

		$jobGridProps = $this->jobGrid->getProps(
			$query_args,
			'Hasil Pencarian',
			'search',
			$total_jobs
		);

		return [
			'layouts' => $this->layouts->getProps(),
			'hero' => $this->hero->getProps(),
			'jobGrid' => $jobGridProps,
		];
	}

	/**
	 * Return schema cards for the current archive/search params.
	 */
	public function viewSearchResultsSchema(): array
	{
		$params = [
			'cari' => $_GET['cari'] ?? '',
			'lokasi' => $_GET['lokasi'] ?? '',
			'gender' => $_GET['gender'] ?? '',
			'pendidikan' => $_GET['pendidikan'] ?? '',
			'sort' => $_GET['sort'] ?? 'desc',
		];

		$paged = max(1, (int) ($_GET['paged'] ?? 1));

		$query_args = JobQuery::searchJobsArgs($params, $paged, 36);

		return $this->jobGrid->getSchemaCard($query_args);
	}
}
