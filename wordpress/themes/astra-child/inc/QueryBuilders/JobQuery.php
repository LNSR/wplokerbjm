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
	public static function latestJobsArgs(int $paged = 1, $posts_per_page = 9): array{
		return [
			'post_type' => 'lowongan',
			'posts_per_page' => $posts_per_page,
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
		$today = date('Y-m-d');
		$seven_days = date('Y-m-d', strtotime('+7 days'));

		return [
			'post_type'      => 'lowongan',
			'posts_per_page' => $per_page,
			'meta_query'     => [
				[
					'key'     => 'status_pekerjaan',
					'value'   => [2, 3],
					'compare' => 'IN',
					'type'    => 'NUMERIC',
				],
				[
					'key'     => 'deadline',
					'value'   => [$today, $seven_days],
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				],
			],
			'post_status'    => 'publish',
			'orderby'        => 'meta_value',
			'meta_key'       => 'deadline',
			'order'          => 'ASC',
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
	public static function searchJobsArgs(array $params, int $paged, int $per_page): array
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

		if (!empty($params['lokasi'])) {
			$lokasi_terms = is_array($params['lokasi'])
				? array_map('sanitize_text_field', $params['lokasi'])
				: [sanitize_text_field($params['lokasi'])];
			$tax_query[] = [
				'taxonomy' => 'lokasi-pekerjaan',
				'field' => 'slug',
				'terms' => $lokasi_terms,
				'operator' => 'IN',
			];
		}
		if (!empty($params['gender'])) {
			$gender_terms = is_array($params['gender'])
				? array_map('sanitize_text_field', $params['gender'])
				: [sanitize_text_field($params['gender'])];
			$tax_query[] = [
				'taxonomy' => 'gender',
				'field' => 'slug',
				'terms' => $gender_terms,
				'operator' => 'IN',
			];
		}
		if (!empty($params['pendidikan'])) {
			$pendidikan_terms = is_array($params['pendidikan'])
				? array_map('sanitize_text_field', $params['pendidikan'])
				: [sanitize_text_field($params['pendidikan'])];
			$tax_query[] = [
				'taxonomy' => 'pendidikan',
				'field' => 'slug',
				'terms' => $pendidikan_terms,
				'operator' => 'IN',
			];
		}
		
		/**
		 * ! NOTE: The 's' parameter search is overridden by the custom SQL in Filters::jobPostsSearchFilter().
		 * The tax_query for 'perusahaan' is still useful for REST or custom queries that do not use 's'.
		 */
		if (!empty($params['cari'])) {
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
				'relation' => 'AND',
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
					'before' => '1 month ago',
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
