<?php

namespace WPLokerBJM\Core\Plugins;

use DI\Attribute\Injectable;
use WPlokerBJM\Core\Container\Attributes\Filter;
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};

/**
 * Rank Math Integration Service
 * Extends RankMath plugin functionality
 * Handles Rank Math SEO plugin integrations including sitemap regeneration
 */
#[Injectable(lazy: true)]
class Rankmath
{
	private static ?bool $isActiveCache = null;
	private static ?array $sitemapUrlsCache = null;

	/**
	 * Check if Rank Math plugin is active (with caching)
	 */
	public static function isActive(): bool
	{
		if (self::$isActiveCache === null) {
			self::$isActiveCache = PluginList::RankMath->isActive();
		}
		return self::$isActiveCache;
	}

	/**
	 * Rank Math outputs incorrect og:image:type for AVIF images.
	 * This filter corrects the type based on the image URL.
	 * @see \RankMath\Frontend\Frontend;
	 * @param mixed $input Either an array (image_array filter) or string (og_image_type filter)
	 * @return mixed Modified input with correct og:image:type
	 */
	#[Filter('rank_math/opengraph/facebook/image_array')]
	public function FixImageTypeOG($input)
	{
		if (!self::isActive()) {
			return $input;
		}

		if (is_array($input)) {
			// Handle image_array filter
			if (isset($input['url']) && strpos($input['url'], '.avif') !== false) {
				$input['type'] = 'image/avif';
			}
			return $input;
		} elseif (is_string($input)) {
			// Handle og_image_type filter - type is already set in image_array
			return $input;
		}

		return $input;
	}

	/**
	 * Rewrite publish URL to use headless/frontend domain before Rank Math
	 * Instant Indexing submits the URL.
	 *
	 * @see ../../../../../../wp-content/plugins/fast-indexing-api/includes/class-instant-indexing.php
	 * @see \RM_GIAPI::publish_post
	 *
	 * @param string $url Original URL (usually from get_permalink()).
	 * @param mixed  $post WP_Post or post ID passed by Rank Math.
	 * @param string $provider Provider identifier (e.g., 'google'|'bing').
	 * @return string Rewritten URL using headless domain.
	 */
	#[Filter('rank_math/indexing_api/publish_url')]
	public function RewritePublishUrl($url, $post = null, $provider = '')
	{
		if (!self::isActive() || empty($url)) {
			return $url;
		}
		$headless = SharedUtils::headlessDomainRedirect();
		$parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url);
		$path = $parts['path'] ?? '/';
		$query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
		return rtrim($headless, '/') . $path . $query;
	}


	/**
	 * Rewrite delete URL to use headless/frontend domain before Rank Math
	 * Instant Indexing submits the delete notification.
	 *
	 * @see ../../../../../../wp-content/plugins/fast-indexing-api/includes/class-instant-indexing.php
	 * @see \RM_GIAPI::delete_post
	 *
	 * @param string $url Original URL to delete (get_permalink()).
	 * @param mixed  $post WP_Post or post ID passed by Rank Math.
	 * @return string Rewritten URL using headless domain.
	 */
	#[Filter('rank_math/indexing_api/delete_url')]
	public function RewriteDeleteUrl($url, $post = null)
	{
		if (!self::isActive() || empty($url)) {
			return $url;
		}
		$headless = SharedUtils::headlessDomainRedirect();
		$parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url);
		$path = $parts['path'] ?? '/';
		$query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
		return rtrim($headless, '/') . $path . $query;
	}

	/**
	 * Ensure Rank Math's SEO Analyzer uses the headless frontend domain for analysis.
	 * Hooks into the analyzer after it sets the default URL and rewrites it.
	 *
	 * @see ../../../../../../wp-content/plugins/seo-by-rank-math/includes/modules/seo-analysis/class-seo-analyzer.php
	 * @param object $analyzer Instance of RankMath\SEO_Analysis\SEO_Analyzer (passed by action).
	 * @return void
	 */
	#[Filter('seo_analysis/after_set_url')]
	public function rewriteSeoAnalyzerInstanceUrl($analyzer): void
	{
		if (!self::isActive()) {
			return;
		}

		if (empty($analyzer) || !property_exists($analyzer, 'analyse_url')) {
			return;
		}

		$original = $analyzer->analyse_url;
		if (empty($original)) {
			return;
		}

		$headless = rtrim(SharedUtils::headlessDomainRedirect(), '/');
		$parts = function_exists('wp_parse_url') ? wp_parse_url($original) : parse_url($original);
		$path = $parts['path'] ?? '/';
		$query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
		$analyzer->analyse_url = $headless . $path . $query;
	}
}