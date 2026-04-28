<?php

namespace WPLokerBJM\QueryBuilders;

use WPLokerBJM\QueryBuilders\TaxonomyQuery;
use WPLokerBJM\Models\Schema\{CustomFields, Taxonomies, PostTypes};
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Log\Logger;

class JobQuery
{
	const array getBaseArgs = [
		'post_type' => PostTypes::POST_TYPE_LOWONGAN,
		'post_status' => 'publish',
	];

	/**
	 * Get WP_Query args for latest jobs.
	 *
	 * @param int $paged
	 * @param int $posts_per_page
	 * @return array
	 */
	public static function latestJobsArgs(int $paged = 1, $posts_per_page = 9): array
	{
		return array_merge(self::getBaseArgs, [
			'posts_per_page' => $posts_per_page,
			'paged' => $paged,
			'orderby' => 'post_date',
			'order' => 'DESC',
		]);
	}

	/**
	 * Get WP_Query args for auto suggestion search.
	 *
	 * @param string $query
	 * @return array
	 */
	public static function autoSuggestionArgs(string $query): array
	{
		return array_merge(self::getBaseArgs, [
			's' => $query,
			'fields' => 'ids',
			'posts_per_page' => 10,
			'no_found_rows' => true,
		]);
	}

	/*
	 * Get WP_Query args for job carousel.
	 *
	 */
	public static function getCarouselArgs(int $per_page): array
	{
		$today = date('Y-m-d');
		$two_weeks = date('Y-m-d', strtotime('+14 days'));

		return array_merge(self::getBaseArgs, [
			'posts_per_page' => $per_page,
			'meta_query' => [
				'relation' => 'OR',
				// Pinned jobs (status 3) - always show
				[
					'key' => CustomFields::STATUS_PEKERJAAN,
					'value' => CustomFields::STATUS_PEKERJAAN_PINNED,
					'compare' => '=',
					'type' => 'NUMERIC',
				],
				// Urgent jobs (status 2) with upcoming deadlines
				[
					'relation' => 'AND',
					[
						'key' => CustomFields::STATUS_PEKERJAAN,
						'value' => CustomFields::STATUS_PEKERJAAN_URGENT,
						'compare' => '=',
						'type' => 'NUMERIC',
					],
					[
						'key' => CustomFields::DEADLINE,
						'value' => [$today, $two_weeks],
						'compare' => 'BETWEEN',
						'type' => 'DATE',
					],
				],
			],
		]);
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
		$sortValue = isset($params['sort']) ? (is_array($params['sort']) ? ($params['sort']['value'] ?? 'desc') : $params['sort']) : 'desc';
		$order = (strtolower($sortValue) === 'asc') ? 'ASC' : 'DESC';

		$args = array_merge(self::getBaseArgs, [
			'posts_per_page' => $per_page,
			'paged' => $paged,
			'orderby' => 'post_date',
			'order' => $order,
		]);

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
	 * IMPORTANT Used for deleting old jobs according context (\WPLokerBJM\Services\PostsManagement\PostsManagement)
	 *
	 * NOTE: This query excludes jobs that have a future 'deadline' meta value —
	 * we want to avoid deleting job postings that are still active.
	 */
	public static function oldJobsArgs(): array
	{
		$today = date('Y-m-d');
		return array_merge(self::getBaseArgs, [
			'posts_per_page' => -1,
			'date_query' => [
				[
					'column' => 'post_date',
					'before' => '1 month ago', // delete jobs older than 1 month
				],
			],
			/* Exclude posts that have a future deadline. We only want to delete posts
			 * that either don't have a deadline or have a deadline that is already past.
			 */
			'meta_query' => [
				'relation' => 'OR',
				// posts without a deadline
				[
					'key' => CustomFields::DEADLINE,
					'compare' => 'NOT EXISTS',
				],
				// posts with deadline less than or equal to today
				[
					'key' => CustomFields::DEADLINE,
					'value' => $today,
					'compare' => '<=',
					'type' => 'DATE',
				],
			],
			'fields' => 'ids',
		]);
	}


	public static function allJobsIdsArgs(): array
	{
		return array_merge(self::getBaseArgs, [
			'posts_per_page' => -1,
			'fields' => 'ids',
		]);
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

        $cache_key = CacheKey::SEARCH_SQL_PREFIX . md5($q);
        $cached = Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
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
                WHERE meta_key = '" . CustomFields::NAMA_PERUSAHAAN . "' AND (meta_value LIKE '%{$q_esc}%' OR meta_value LIKE '%{$q_html}%')
            ) OR ";
            $sql .= "{$posts}.ID IN (
                SELECT object_id FROM {$term_relationships}
                INNER JOIN {$term_taxonomy} ON {$term_taxonomy}.term_taxonomy_id = {$term_relationships}.term_taxonomy_id
                INNER JOIN {$terms} ON {$terms}.term_id = {$term_taxonomy}.term_id
                WHERE {$term_taxonomy}.taxonomy = '" . Taxonomies::PERUSAHAAN . "'
                AND {$terms}.name LIKE '%{$q_esc}%'
            )";
            $sql .= ")";

            Cache::set($cache_key, $sql, 86400); // Cache for 1 day
            return $sql;
        } catch (\Exception $e) {
            Logger::error('Query', 'JobQuery::buildPostsSearchSql error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get the last modified date of the latest lowongan post.
     *
     * This method tracks the most recent modification timestamp for the 'lowongan' post type,
     * which can be used to determine if new job postings have been added or updated since
     * the page was loaded. Useful for implementing refresh logic or cache invalidation
     * based on content changes.
     *
     * @return string The GMT modified date of the latest lowongan post, or current GMT time if none exist.
     */
    public static function getLastModifiedDate(): string
    {
        $cache_key = CacheKey::JOB_LAST_MODIFIED;
        $cached = Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $latest = get_posts([
            'post_type' => PostTypes::POST_TYPE_LOWONGAN,
            'numberposts' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        if (!empty($latest)) {
            $post = $latest[0];
            if (is_object($post) && property_exists($post, 'post_modified_gmt')) {
                $result = $post->post_modified_gmt;
            } else {
                $result = gmdate('c');
            }
        } else {
            $result = gmdate('c');
        }

        Cache::set($cache_key, $result, 86400); // Cache for 1 day
        return $result;
    }

	/**
	 * Return args suitable for `get_posts()` to fetch attachment IDs for a parent post.
	 *
	 * @param int $parent_id
	 * @param bool $only_ids If true, return only IDs (fields => 'ids').
	 * @return array
	 */
	public static function byParentArgs(int $parent_id, bool $only_ids = true): array
	{
		$args = [
			'post_parent' => $parent_id,
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => 'any',
		];

		if ($only_ids) {
			$args['fields'] = 'ids';
		}

		return $args;
	}

	/**
	 * Get WP_Query args to check if a job post exists in trash by name.
	 *
	 * @param string $post_name
	 * @return array
	 */
	public static function getTrashedJobByNameArgs(string $post_name): array
	{
		return [
			'name' => $post_name,
			'post_type' => PostTypes::POST_TYPE_LOWONGAN,
			'post_status' => 'trash',
			'numberposts' => 1,
			'fields' => 'ids',
		];
	}

}
