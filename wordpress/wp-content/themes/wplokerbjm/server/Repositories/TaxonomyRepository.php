<?php

namespace WPLokerBJM\Repositories;

use WPLokerBJM\Models\Schema\Taxonomies;

class TaxonomyRepository
{

	public $metaBoxesTaxonomies = [
		Taxonomies::PERUSAHAAN,
		Taxonomies::KATEGORI_LOWONGAN,
		Taxonomies::LOKASI_PEKERJAAN,
		Taxonomies::JENIS_PEKERJAAN,
		Taxonomies::GENDER,
		Taxonomies::PENDIDIKAN,
	];

	/**
	 * Get job taxonomies
	 *
	 * @param int $post_id Post ID
	 * @return array The data representing the taxonomy data
	 */
	public function getMetaBoxTaxonomies(int $post_id): array
	{
		$result = [];
		foreach ($this->metaBoxesTaxonomies as $taxonomy) {
			$terms = get_the_terms($post_id, $taxonomy);
			if (is_wp_error($terms) || empty($terms) || $terms === false) {
				$result[$taxonomy] = [];
			} else {
				$result[$taxonomy] = is_array($terms) ? $terms : [];
			}
		}

		return $result;
	}

	public function getTaxonomyTerms(): array
	{
		$terms = [];
		foreach ($this->metaBoxesTaxonomies as $taxonomy) {
			$terms[$taxonomy] = get_terms([
				'taxonomy' => $taxonomy,
				'hide_empty' => true,
			]);
		}
		return $terms;
	}
}
