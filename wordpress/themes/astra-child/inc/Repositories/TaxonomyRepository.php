<?php

namespace AstraChild\Repositories;

use AstraChild\Contracts\DataProviderInterface;
use AstraChild\Models\TaxonomyEntity;

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
		return new TaxonomyEntity(
			perusahaan_taxo: get_the_terms($post_id, 'perusahaan'),
			kategori_lowongan_taxo: get_the_terms($post_id, 'kategori-lowongan'),
			lokasi_taxo: get_the_terms($post_id, 'lokasi-pekerjaan'),
			jenis_pekerjaan_taxo: get_the_terms($post_id, 'jenis-pekerjaan'),
			gender_taxo: get_the_terms($post_id, 'gender'),
			pendidikan_taxo: get_the_terms($post_id, 'pendidikan')
		);
	}

	public function getTaxonomyTerms(): array
	{
		return [
			'perusahaan_terms' => get_terms([
				'taxonomy' => 'perusahaan',
				'hide_empty' => true,
				'parent' => 0
			]),
			'kategori_lowongan_terms' => get_terms([
				'taxonomy' => 'kategori-lowongan',
				'hide_empty' => true,
				'parent' => 0
			]),
			'lokasi_terms' => get_terms([
				'taxonomy' => 'lokasi-pekerjaan',
				'hide_empty' => true,
				'parent' => 0
			]),
			'jenis_pekerjaan_terms' => get_terms([
				'taxonomy' => 'jenis-pekerjaan',
				'hide_empty' => true,
				'parent' => 0
			]),
			'gender_terms' => get_terms([
				'taxonomy' => 'gender',
				'hide_empty' => true,
				'parent' => 0
			]),
			'pendidikan_terms' => get_terms([
				'taxonomy' => 'pendidikan',
				'hide_empty' => true,
				'parent' => 0
			]),
			'pengalaman_terms' => get_terms([
				'taxonomy' => 'pengalaman',
				'hide_empty' => true,
				'parent' => 0
			])
		];
	}
}
