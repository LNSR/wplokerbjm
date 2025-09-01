<?php

namespace AstraChild\Repositories;

use AstraChild\Contracts\DataProviderInterface;
use AstraChild\Models\TaxonomyEntity;
use AstraChild\Core\Cache;

class TaxonomyRepository implements DataProviderInterface
{

	public function __construct()
	{
		// Constructor code if needed
	}

	/**
	 * Get job taxonomies
	 *
	 * @param int $post_id Post ID
	 * @return TaxonomyEntity
	 */
	public function getMetaBoxData(int $post_id): TaxonomyEntity
	{
		$cacheKey = 'taxonomies_job_data_' . $post_id;
		$cached = Cache::get($cacheKey);
		if ($cached !== false) {
			return $cached;
		}
		$entity = new TaxonomyEntity(
			perusahaan_taxo: get_the_terms($post_id, 'perusahaan'),
			kategori_lowongan_taxo: get_the_terms($post_id, 'kategori-lowongan'),
			lokasi_taxo: get_the_terms($post_id, 'lokasi-pekerjaan'),
			jenis_pekerjaan_taxo: get_the_terms($post_id, 'jenis-pekerjaan'),
			gender_taxo: get_the_terms($post_id, 'gender'),
			pendidikan_taxo: get_the_terms($post_id, 'pendidikan')
		);
		Cache::set($cacheKey, $entity, 86400); // Cache for 24 hours
		return $entity;
	}

	public function getTaxonomyTerms(): array
	{
		$cached_terms = Cache::get('taxonomy_terms_all');
		if ($cached_terms !== false) {
			return $cached_terms;
		}

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
		Cache::set('taxonomy_terms_all', $terms, 86400); // Cache for 24 hours
		return $terms;
	}
}
