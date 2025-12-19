<?php

namespace WPLokerBJM\Repositories;

use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Core\Cache;

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
		$cache_key = 'post_taxonomies_' . $post_id;
		$cached = Cache::get($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		$result = [];
		foreach ($this->metaBoxesTaxonomies as $taxonomy) {
			$terms = get_the_terms($post_id, $taxonomy);
			if (is_wp_error($terms) || empty($terms) || $terms === false) {
				$result[$taxonomy] = [];
			} else {
				$result[$taxonomy] = is_array($terms) ? $terms : [];
			}
		}

		Cache::set($cache_key, $result, 3600); // Cache for 1 hour
		return $result;
	}

	public function getTaxonomyTerms(): array
	{
		$cache_key = 'all_taxonomy_terms';
		$cached = Cache::get($cache_key);
		if ($cached !== false) {
			return $cached;
		}

		$terms = [];
		foreach ($this->metaBoxesTaxonomies as $taxonomy) {
			$terms[$taxonomy] = get_terms([
				'taxonomy' => $taxonomy,
				'hide_empty' => true,
			]);
		}

		Cache::set($cache_key, $terms, 3600); // Cache for 1 hour
		return $terms;
	}
}
