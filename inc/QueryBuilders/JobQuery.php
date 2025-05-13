<?php

namespace AstraChild\QueryBuilders;

class JobQuery
{
	/**
	 * Get WP_Query args for latest jobs.
	 *
	 * @param int $paged
	 * @param int $per_page
	 * @return array
	 */
	public static function latestJobsArgs(int $paged = 1, int $per_page = 9): array
	{
		return [
			'post_type' => 'lowongan',
			'posts_per_page' => $per_page,
			'paged' => $paged,
			'orderby' => 'date',
			'order' => 'DESC',
			'post_status' => 'publish',
		];
	}

	/**
	 * Get WP_Query args for auto suggestion search.
	 *
	 * @param string $query
	 * @return array
	 */
	public static function autoSuggestionArgs(string $query): array
	{
		return [
			'post_type' => 'lowongan',
			'post_status' => 'publish',
			's' => $query,
			'fields' => 'ids',
			'posts_per_page' => 10,
			'no_found_rows' => true,
		];
	}

	/*
	 * Get WP_Query args for job carousel.
	 *
	 */
	public static function getCarouselArgs(int $per_page): array
	{
		return [
			'post_type' => 'lowongan',
			'posts_per_page' => $per_page,
			'meta_query' => [
				[
					'key' => 'status_pekerjaan',
					'value' => [2, 3],
					'compare' => 'IN',
					'type' => 'NUMERIC',
				],
			],
			'post_status' => 'publish',
			'orderby' => 'date',
			'order' => 'DESC',
		];
	}

	/**
	 * Get WP_Query args for searching jobs.
	 *
	 * @param array $params
	 * @param int $paged
	 * @param int $per_page
	 * @return array
	 */
	public static function searchJobsArgs(array $params, int $paged = 1, int $per_page = 9): array
	{
		$order = (isset($params['sort']) && strtolower($params['sort']) === 'asc') ? 'ASC' : 'DESC';

		$args = [
			'post_type' => 'lowongan',
			'posts_per_page' => $per_page,
			'paged' => $paged,
			'orderby' => 'date',
			'order' => $order,
			'post_status' => 'publish',
		];

		$tax_query = [];

		if (! empty($params['lokasi'])) {
			$tax_query[] = [
				'taxonomy' => 'lokasi-pekerjaan',
				'field' => 'slug',
				'terms' => sanitize_text_field($params['lokasi']),
			];
		}
		if (! empty($params['gender'])) {
			$tax_query[] = [
				'taxonomy' => 'gender',
				'field' => 'slug',
				'terms' => sanitize_text_field($params['gender']),
			];
		}
		if (! empty($params['pendidikan'])) {
			$tax_query[] = [
				'taxonomy' => 'pendidikan',
				'field' => 'slug',
				'terms' => sanitize_text_field($params['pendidikan']),
			];
		}

		if (! empty($params['cari'])) {
			$search_term = sanitize_text_field($params['cari']);
			$tax_query[] = [
				'taxonomy' => 'perusahaan',
				'field'    => 'name',
				'terms'    => $search_term,
				'operator' => 'LIKE',
			];
			$args['s'] = $search_term;
		}

		if ($tax_query) {
			$args['tax_query'] = [
				'relation' => !empty($params['cari']) ? 'OR' : 'AND',
				...$tax_query
			];
		}

		return $args;
	}

	/**
	 * * IMPORTANT Used for deleting old jobs according context (\AstraChild\Services\PostsManagement\PostsManagement)
	 */
	public static function oldJobsArgs(): array
	{
		return [
			'post_type'      => 'lowongan',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'date_query'     => [
				[
					'column' => 'post_date',
					'before' => '3 months ago',
				],
			],
			'fields' => 'ids',
		];
	}

	public static function allJobsIdsArgs(): array
	{
		return [
			'post_type'      => 'lowongan',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];
	}
}
