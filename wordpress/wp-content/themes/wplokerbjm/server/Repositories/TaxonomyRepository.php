<?php

namespace WPLokerBJM\Repositories;

use WPLokerBJM\Contracts\DataProviderInterface;

class TaxonomyRepository implements DataProviderInterface
{
	/**
	 * Get job taxonomies
	 *
	 * @param int $post_id Post ID
	 * @return array The data representing the taxonomy data
	 */
	public function getMetaBoxData(int $post_id): array
	{
		$map = [
			'perusahaan_taxo' => 'perusahaan',
			'kategori_lowongan_taxo' => 'kategori-lowongan',
			'lokasi_taxo' => 'lokasi-pekerjaan',
			'jenis_pekerjaan_taxo' => 'jenis-pekerjaan',
			'gender_taxo' => 'gender',
			'pendidikan_taxo' => 'pendidikan',
		];

		$result = [];
		foreach ($map as $key => $taxonomy) {
			$terms = get_the_terms($post_id, $taxonomy);
			if (is_wp_error($terms) || empty($terms) || $terms === false) {
				$result[$key] = [];
			} else {
				$result[$key] = is_array($terms) ? $terms : [];
			}
		}

		return $result;
	}

	public function getTaxonomyTerms(): array
	{

		$terms = [
			'perusahaan_terms' => get_terms([
				'taxonomy' => 'perusahaan',
				'hide_empty' => true,
			]),
			'kategori_lowongan_terms' => get_terms([
				'taxonomy' => 'kategori-lowongan',
				'hide_empty' => true,
			]),
			'lokasi_terms' => get_terms([
				'taxonomy' => 'lokasi-pekerjaan',
				'hide_empty' => true,
			]),
			'jenis_pekerjaan_terms' => get_terms([
				'taxonomy' => 'jenis-pekerjaan',
				'hide_empty' => true,
			]),
			'gender_terms' => get_terms([
				'taxonomy' => 'gender',
				'hide_empty' => true,
			]),
			'pendidikan_terms' => get_terms([
				'taxonomy' => 'pendidikan',
				'hide_empty' => true,
			]),
			'pengalaman_terms' => get_terms([
				'taxonomy' => 'pengalaman',
				'hide_empty' => true,
			])
		];
		return $terms;
	}
}
