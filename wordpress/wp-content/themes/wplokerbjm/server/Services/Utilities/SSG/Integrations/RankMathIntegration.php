<?php

namespace WPLokerBJM\Services\Utilities\SSG\Integrations;

use WPLokerBJM\Core\TransientCache;

/**
 * Rank Math Integration Service
 *
 * Handles Rank Math SEO plugin integrations including sitemap regeneration
 */
class RankMathIntegration {
	private static ?bool $isActiveCache = null;
	private static ?array $sitemapUrlsCache = null;

	/**
	 * Check if Rank Math plugin is active (with caching)
	 */
	public static function isActive(): bool {
		if ( self::$isActiveCache === null ) {
			self::$isActiveCache = function_exists( 'rank_math' );
		}
		return self::$isActiveCache;
	}

	/**
	 * Debounce sitemap regeneration to prevent rapid successive calls
	 */
	private static function debounceSitemapRegeneration( string $debounceKey, int $duration, string $skipMessage ): bool {
		$lastRegeneration = TransientCache::get( $debounceKey );
		if ( $lastRegeneration !== false ) {
			error_log( $skipMessage );
			return false;
		}
		TransientCache::set( $debounceKey, time(), $duration );
		return true;
	}

	/**
	 * Force Rank Math sitemap regeneration for published posts
	 */
	public static function regenerateSitemap( int $post_id, \WP_Post $post ): void {
		// Only regenerate for published posts
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		// Check if Rank Math is active
		if ( ! self::isActive() ) {
			return;
		}

		// Debounce sitemap regeneration to prevent rapid successive calls
		$debounceKey = "rankmath_sitemap_debounce_{$post_id}";
		if ( ! self::debounceSitemapRegeneration( $debounceKey, 300, "Rank Math sitemap regeneration skipped for post {$post_id} (debounced)" ) ) {
			return;
		}

		// Force sitemap cache clear and regeneration
		if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}

		// Alternative method using Rank Math's action
		do_action( 'rank_math/sitemap/generate_after_update', $post_id );

		// Log the sitemap regeneration
		error_log( "Rank Math sitemap regeneration triggered for post {$post_id} ({$post->post_title})" );
	}

	/**
	 * Force Rank Math sitemap regeneration on post delete/trash
	 */
	public static function regenerateSitemapOnDelete( int $post_id ): void {
		// Check if Rank Math is active
		if ( ! self::isActive() ) {
			return;
		}

		// Debounce sitemap regeneration
		$debounceKey = "rankmath_sitemap_delete_debounce_{$post_id}";
		if ( ! self::debounceSitemapRegeneration( $debounceKey, 180, "Rank Math sitemap regeneration skipped for deleted post {$post_id} (debounced)" ) ) {
			return;
		}

		// Force sitemap cache clear and regeneration
		if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}

		// Alternative method using Rank Math's action
		do_action( 'rank_math/sitemap/generate_after_update', $post_id );

		// Log the sitemap regeneration
		error_log( "Rank Math sitemap regeneration triggered for deleted/trashed post {$post_id}" );
	}

	/**
	 * Force complete sitemap regeneration
	 */
	public static function regenerateFullSitemap(): void {
		if ( ! self::isActive() ) {
			return;
		}

		// Debounce full sitemap regeneration
		$debounceKey = "rankmath_full_sitemap_debounce";
		if ( ! self::debounceSitemapRegeneration( $debounceKey, 600, "Rank Math full sitemap regeneration skipped (debounced)" ) ) {
			return;
		}

		// Clear all sitemap caches
		if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}

		// Trigger full sitemap regeneration
		do_action( 'rank_math/sitemap/generate_after_update', 0 );

		error_log( 'Rank Math full sitemap regeneration triggered' );
	}

	/**
	 * Get sitemap URLs (with caching)
	 */
	public static function getSitemapUrls(): array {
		if ( self::$sitemapUrlsCache === null ) {
			if ( ! self::isActive() ) {
				self::$sitemapUrlsCache = [];
				return self::$sitemapUrlsCache;
			}

			$urls = [];

			// Get main sitemap index
			$sitemap_index = home_url( '/sitemap_index.xml' );
			$urls[] = $sitemap_index;

			// Get post sitemaps
			$post_types = get_post_types( [ 'public' => true ] );
			foreach ( $post_types as $post_type ) {
				$urls[] = home_url( "/{$post_type}-sitemap.xml" );
			}

			// Get taxonomy sitemaps
			$taxonomies = get_taxonomies( [ 'public' => true ] );
			foreach ( $taxonomies as $taxonomy ) {
				$urls[] = home_url( "/{$taxonomy}-sitemap.xml" );
			}

			self::$sitemapUrlsCache = $urls;
		}

		return self::$sitemapUrlsCache;
	}

	/**
	 * Clear internal caches (useful for testing or forced refresh)
	 */
	public static function clearCaches(): void {
		self::$isActiveCache = null;
		self::$sitemapUrlsCache = null;
	}

	/**
	 * Clear all Rank Math related transients (for maintenance)
	 */
	public static function clearAllTransients(): void {
		$deleted = TransientCache::deletePattern( 'rankmath_' );

		self::clearCaches();

		error_log( "Rank Math integration transients cleared: {$deleted} entries deleted" );
	}
}
