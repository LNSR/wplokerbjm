<?php

namespace WPLokerBJM\Repositories;

use WPLokerBJM\Models\Schema\Taxonomies;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};

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
		$cache_key = CacheKey::POST_TAXONOMIES_PREFIX . $post_id;
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

		Cache::set($cache_key, $result, 86400); // Cache for 1 day
		return $result;
	}

	/**
	 * @return array<string, list<\WP_Term>>
	 */
	public function getTaxonomyTerms(?bool $showEmpty = false): array
	{
		$terms = [];
		foreach ($this->metaBoxesTaxonomies as $taxonomy) {
			$terms[$taxonomy] = get_terms([
				'taxonomy' => $taxonomy,
				'hide_empty' => $showEmpty,
			]);
		}

		return $terms;
	}
}
