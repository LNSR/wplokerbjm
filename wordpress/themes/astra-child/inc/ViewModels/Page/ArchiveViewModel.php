<?php

namespace AstraChild\ViewModels\Page;
use AstraChild\Components\Hero;
use AstraChild\Components\JobGrid;

class ArchiveViewModel {

	public function __construct(
		private Hero $hero,
		private JobGrid $jobGrid
	) {
	}

	public function viewHero() {
		return $this->hero->render();
	}

	public function viewSearchResults() {
		$params = [ 
			'cari' => $_GET['cari'] ?? '',
			'lokasi' => $_GET['lokasi'] ?? '',
			'gender' => $_GET['gender'] ?? '',
			'pendidikan' => $_GET['pendidikan'] ?? '',
			'sort' => $_GET['sort'] ?? 'desc',
		];
		$paged = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$query_args = \AstraChild\QueryBuilders\JobQuery::searchJobsArgs( $params, $paged, 36);
		$jobs_query = new \WP_Query( $query_args );
		$total_jobs = $jobs_query->found_posts;
		wp_reset_postdata();

		return $this->jobGrid->render(
			$query_args,
			'Hasil Pencarian',
			'search',
			$total_jobs
		);
	}

	public function viewFloatingActionButton(): string {
		return '<div id="floating-action-button"></div>';
	}

	public function viewFloatingAstraColorSwitchButton(): string {
		return '<div id="color-switch-button"></div>';
	}
}
