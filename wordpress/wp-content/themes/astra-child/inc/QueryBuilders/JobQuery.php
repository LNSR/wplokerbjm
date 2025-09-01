<?php

namespace AstraChild\QueryBuilders;

use AstraChild\QueryBuilders\TaxonomyQuery;

class JobQuery
{
	/**
	 * Get WP_Query args for latest jobs.
	 *
	 * @param int $paged
	 * @param int $per_page
	 * @return array
	 */
	public static function latestJobsArgs(int $paged = 1, $posts_per_page = 9): array
	{
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
			'post_type' => 'lowongan',
			'posts_per_page' => $per_page,
			'meta_query' => [
				[
					'key' => 'status_pekerjaan',
					'value' => [2, 3],
					'compare' => 'IN',
					'type' => 'NUMERIC',
				],
				[
					'key' => 'deadline',
					'value' => [$today, $seven_days],
					'compare' => 'BETWEEN',
					'type' => 'DATE',
				],
			],
			'post_status' => 'publish',
			'orderby' => 'meta_value',
			'meta_key' => 'deadline',
			'order' => 'ASC',
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

		// Delegate taxonomy parts construction to TaxonomyQuery for separation of concerns.
		$tax_query_parts = TaxonomyQuery::jobTaxQueryParts($params);
		if (!empty($params['cari'])) {
			// preserve previous behavior where 'cari' also sets 's'
			$args['s'] = sanitize_text_field($params['cari']);
		}

		if ($tax_query_parts) {
			$args['tax_query'] = [
				'relation' => 'AND',
				...$tax_query_parts,
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
			'post_type' => 'lowongan',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'date_query' => [
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
			'post_type' => 'lowongan',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
		];
	}

	/**
	 * Build a SQL fragment for the `posts_search` filter used for job post searches.
	 *
	 * This returns an escaped SQL string (starting with a leading " AND (...)")
	 * suitable for appending to the `$search` value in the `posts_search` filter.
	 *
	 * @param \wpdb $wpdb
	 * @param string $q raw search string
	 * @return string SQL fragment (empty string when $q is empty)
	 */
	public static function buildPostsSearchSql(\wpdb $wpdb, string $q): string
	{
		if ($q === '') {
			return '';
		}

		// Safely escape the search term for LIKE queries
		$q_esc = esc_sql($wpdb->esc_like($q));
		$q_html = esc_sql($wpdb->esc_like(htmlentities($q, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

		$posts = $wpdb->posts;
		$postmeta = $wpdb->postmeta;
		$terms = $wpdb->terms;
		$term_taxonomy = $wpdb->term_taxonomy;
		$term_relationships = $wpdb->term_relationships;

		$sql = " AND (";
		$sql .= "{$posts}.post_title LIKE '%{$q_esc}%' OR ";
		$sql .= "{$posts}.post_title LIKE '%{$q_html}%' OR ";
		$sql .= "{$posts}.ID IN (
			SELECT post_id FROM {$postmeta}
			WHERE meta_key = 'nama_perusahaan' AND (meta_value LIKE '%{$q_esc}%' OR meta_value LIKE '%{$q_html}%')
		) OR ";
		$sql .= "{$posts}.ID IN (
			SELECT object_id FROM {$term_relationships}
			INNER JOIN {$term_taxonomy} ON {$term_taxonomy}.term_taxonomy_id = {$term_relationships}.term_taxonomy_id
			INNER JOIN {$terms} ON {$terms}.term_id = {$term_taxonomy}.term_id
			WHERE {$term_taxonomy}.taxonomy = 'perusahaan'
			AND {$terms}.name LIKE '%{$q_esc}%'
		)";
		$sql .= ")";

		return $sql;
	}
}
