<?php

namespace AstraChild\ViewModels\Page;

use AstraChild\QueryBuilders\JobQuery;

class HomepageViewModel
{

	public function __construct()
	{

	}

	public function viewHero()
	{
		return \AstraChild\Resources\Components\Hero::render();
	}

	public function viewCarousel()
	{
		return \AstraChild\Resources\Components\JobCarousel::render();
	}

	public function viewCategoryGrid()
	{
		ob_start();
		?>
		<section class="px-4 py-8">
			<h2 class="text-2xl font-semibold !mb-6">Kategori Populer</h2>
			<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
				<a href="#"
					class="category-card group border border-blue-400 rounded-lg p-4 flex flex-col items-center justify-center h-24 hover:border-blue-700 transition-colors duration-200 hover:shadow-lg hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-400 active:scale-100">
					<i
						class="fas fa-laptop-code text-3xl text-blue-500 group-hover:text-blue-700 transition-colors duration-200"></i>
					<span class="mt-2 text-center group-hover:text-blue-700 transition-colors duration-200">IT</span>
				</a>
				<a href="#"
					class="category-card group border border-blue-400 rounded-lg p-4 flex flex-col items-center justify-center h-24 hover:border-blue-700 transition-colors duration-200 hover:shadow-lg hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-400 active:scale-100">
					<i class="fas fa-store text-3xl text-blue-500 group-hover:text-blue-700 transition-colors duration-200"></i>
					<span class="mt-2 text-center group-hover:text-blue-700 transition-colors duration-200">Retail</span>
				</a>
				<a href="#"
					class="category-card group border border-blue-400 rounded-lg p-4 flex flex-col items-center justify-center h-24 hover:border-blue-700 transition-colors duration-200 hover:shadow-lg hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-400 active:scale-100">
					<i
						class="fas fa-stethoscope text-3xl text-blue-500 group-hover:text-blue-700 transition-colors duration-200"></i>
					<span class="mt-2 text-center group-hover:text-blue-700 transition-colors duration-200">Kesehatan</span>
				</a>
				<a href="#"
					class="category-card group border border-blue-400 rounded-lg p-4 flex flex-col items-center justify-center h-24 hover:border-blue-700 transition-colors duration-200 hover:shadow-lg hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-400 active:scale-100">
					<i
						class="fas fa-utensils text-3xl text-blue-500 group-hover:text-blue-700 transition-colors duration-200"></i>
					<span class="mt-2 text-center group-hover:text-blue-700 transition-colors duration-200">Kuliner</span>
				</a>
				<a href="#"
					class="category-card group border border-blue-400 rounded-lg p-4 flex flex-col items-center justify-center h-24 hover:border-blue-700 transition-colors duration-200 hover:shadow-lg hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-400 active:scale-100">
					<i
						class="fas fa-graduation-cap text-3xl text-blue-500 group-hover:text-blue-700 transition-colors duration-200"></i>
					<span class="mt-2 text-center group-hover:text-blue-700 transition-colors duration-200">Pendidikan</span>
				</a>
				<a href="#"
					class="category-card group border border-blue-400 rounded-lg p-4 flex flex-col items-center justify-center h-24 hover:border-blue-700 transition-colors duration-200 hover:shadow-lg hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-400 active:scale-100">
					<i
						class="fas fa-ellipsis-h text-3xl text-blue-500 group-hover:text-blue-700 transition-colors duration-200"></i>
					<span class="mt-2 text-center group-hover:text-blue-700 transition-colors duration-200">Lainnya</span>
				</a>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function viewFeaturedJobs()
	{
		return \AstraChild\Resources\Components\JobGrid::render(
			JobQuery::latestJobsArgs(1, 12),
			'Lowongan Terbaru',
			'latest'
		);
	}

	public function viewFloatingActionButton(): string
	{
		return '<div id="floating-action-button"></div>';
	}

	public function viewFloatingAstraColorSwitchButton(): string
	{
		return '<div id="color-switch-button"></div>';
	}
}
