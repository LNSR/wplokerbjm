<?php

namespace AstraChild\Presenters\Page;
use AstraChild\Presenters\Components\Hero;
use AstraChild\Presenters\Components\JobGrid;
use AstraChild\QueryBuilders\JobQuery;

class ArchivePresenter
{

	public function __construct(
		private Hero $hero,
		private JobGrid $jobGrid,
		private JobQuery $jobQuery,
	) {
	}

	private function searchParams(): array
	{
		return [
			'cari' => $_GET['cari'] ?? '',
			'lokasi' => $_GET['lokasi'] ?? '',
			'gender' => $_GET['gender'] ?? '',
			'pendidikan' => $_GET['pendidikan'] ?? '',
			'sort' => $_GET['sort'] ?? 'desc',
		];
	}

	/**
	 * Return schema cards for the current archive/search params.
	 */
	public function viewSearchResultsSchema(): array
	{
		$params = $this->searchParams();

		$paged = max(1, (int) ($_GET['paged'] ?? 1));


		$query_args = JobQuery::searchJobsArgs($params, $paged, 36);

		$schema = $this->jobGrid->getSchemaCard($query_args);

		return $schema;
	}

	/**
	 * Build props for frontend hydration for archive / search pages.
	 * Returns an array similar to HomepagePresenter::getProps()
	 */
	public function viewSearchResultsProps(): array
	{
		$params = $this->searchParams();

		$paged = max(1, (int) ($_GET['paged'] ?? 1));


		$query_args = JobQuery::searchJobsArgs($params, $paged, 36);

		$jobs_query = new \WP_Query($query_args);
		$total_jobs = $jobs_query->found_posts;
		wp_reset_postdata();

		$jobGridProps = $this->jobGrid->getProps(
			$query_args,
			'Hasil Pencarian',
			'search',
			$total_jobs
		);
		$props = [
			'logo' => get_custom_logo(),
			'hero' => $this->hero->getProps(),
			'jobGrid' => $jobGridProps,
		];

		return $props;
	}
}