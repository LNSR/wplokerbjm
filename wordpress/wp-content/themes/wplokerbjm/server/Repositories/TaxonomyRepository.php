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
	public function getTaxonomyTerms(bool $hideEmpty = true): array
	{
		$terms = [];
		foreach ($this->metaBoxesTaxonomies as $taxonomy) {
			$terms[$taxonomy] = get_terms([
				'taxonomy' => $taxonomy,
				'hide_empty' => $hideEmpty,
			]);
		}

		return $terms;
	}

	/**
	 * Get all taxonomy options for the specified taxonomies.
	 *
	 * @param string[] $taxonomies
	 * @return array<string, list<array{id: int, name: string, slug: string, parent: int}>>
	 */
	public function getTaxonomyOptions(array $taxonomies): array
	{
		$options = [];

		foreach ($taxonomies as $taxonomy) {
			$terms = get_terms([
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
				'orderby' => 'name',
				'order' => 'ASC',
			]);

			if (is_wp_error($terms) || !is_array($terms)) {
				$options[$taxonomy] = [];
				continue;
			}

			$options[$taxonomy] = array_values(array_map(
				static fn($term): array => [
					'id' => (int) $term->term_id,
					'name' => html_entity_decode((string) $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					'slug' => (string) $term->slug,
					'parent' => isset($term->parent) ? (int) $term->parent : 0,
				],
				$terms
			));
		}
		return $options;
	}
	    /**
     * Return args to fetch all terms for a taxonomy.
     *
     * @param 'all'|'ids'|'names'|'slugs'|'count'|'id=>parent'|'id=>name'|'id=>slug'|'tt_ids'|'all_with_object_id' $field
     * @return \WP_Term[]|int[]|string[]|string|\WP_Error
     */
    public function allTaxonomiesTerms(string $taxonomy, string $field = 'all'): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => $field,
        ]);
        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }
        return $terms;
    }
}
