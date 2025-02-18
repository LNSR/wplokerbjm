<?php

namespace AstraChild\Resources\Components;

use AstraChild\Core\Container;
use AstraChild\Repositories\TaxonomyRepository;

class Hero
{
	public static function render()
	{
		/** @var TaxonomyRepository $repo */
		$repo = Container::getContainer()->get(TaxonomyRepository::class);
		$terms = $repo->getTaxonomyTerms();

		$lokasi_terms = $terms['lokasi_terms'] ?? [];
		$gender_terms = $terms['gender_terms'] ?? [];
		$pendidikan_terms = $terms['pendidikan_terms'] ?? [];

		$current_search = isset($_GET['cari']) ? sanitize_text_field($_GET['cari']) : '';
		$current_lokasi = isset($_GET['lokasi']) ? sanitize_text_field($_GET['lokasi']) : '';
		$current_gender = isset($_GET['gender']) ? sanitize_text_field($_GET['gender']) : '';
		$current_pendidikan = isset($_GET['pendidikan']) ? sanitize_text_field($_GET['pendidikan']) : '';
		$current_sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'desc';

		ob_start();
?>
		<section class="mx-auto px-4 py-8 text-center">
			<h1 class="text-3xl md:text-5xl !font-bold !mb-2">Temukan Lowongan Kerja Terbaru di Banjarmasin</h1>
			<p class="mb-8 text-lg !text-semibold">Update setiap hari, mudah diakses, dan gratis!</p>

			<div class="border-2 border-blue-500 rounded-xl p-4 md:p-6">
				<?= SearchForm::render($lokasi_terms, $gender_terms, $pendidikan_terms, $current_search, $current_lokasi, $current_gender, $current_pendidikan,  $current_sort); ?>
			</div>
		</section>
<?php
		return ob_get_clean();
	}
}
