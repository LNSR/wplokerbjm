<?php

namespace WPLokerBJM\Core\Plugins\ThirdParty;

use DI\Attribute\Injectable;
use WPLokerBJM\Core\Plugins\PluginConfigInterface;
use WPlokerBJM\Core\Container\Attributes\Filter;
use WPLokerBJM\Shared\Utilities\{SharedUtils, PluginList};
use RankMath\SEO_Analysis\SEO_Analyzer;
use RankMath\OpenGraph\Image;

/**
 * Rank Math Integration Service
 * Extends RankMath plugin functionality
 * Handles Rank Math SEO plugin integrations including sitemap regeneration
 */
final class Rankmath implements PluginConfigInterface
{
	private static ?bool $isActiveCache = null;
	private static ?array $sitemapUrlsCache = null;

	/**
	 * Check if Rank Math plugin is active (with caching)
	 */
	public static function isActive(): bool
	{
		self::$isActiveCache ??= PluginList::RankMath->isActive();
		return self::$isActiveCache;
	}
	/**
	 * Checks if the given URL is valid (not empty).
	 * @param string $url The URL to check.
	 * @return bool Returns true if the URL is valid, false otherwise.
	 */
	private static function checkUrl(string $url): bool
	{
		if (empty($url)) {
			return false;
		}
		return true;
	}

	/**
	 * Rank Math outputs incorrect og:image:type for AVIF images.
	 * This filter corrects the type based on the image URL.
	 * @see Image::add_image();
	 * @param array|string $input Either an array (image_array filter) or string (og_image_type filter)
	 * @return array|string Modified input with correct og:image:type
	 */
	#[Filter('rank_math/opengraph/facebook/image_array', executeIf: static function (array|string $input): bool {
				if (is_array($input) && isset($input['url']) && strpos($input['url'], '.avif') !== false) {
				return true;
				}
			return false;
			}
	)]
	public function FixImageTypeOG(array|string $input): array|string
	{
		$input['type'] = 'image/avif';
		return $input;
	}

	/**
	 * Rewrite publish URL to use headless/frontend domain before Rank Math
	 * Instant Indexing submits the URL.
	 *
	 * @see \RM_GIAPI::publish_post
	 *
	 * @param string $url Original URL (usually from get_permalink()).
	 * @param array|\WP_Post|null  $post WP_Post or post ID passed by Rank Math.
	 * @param string $provider Provider identifier (e.g., 'google'|'bing').
	 * @return string Rewritten URL using headless domain.
	 */
	#[Filter('rank_math/indexing_api/publish_url',
		executeIf: static function (string $url): bool {
				return self::checkUrl($url);
				}
	)]
	public function RewritePublishUrl(string $url, array|\WP_Post|null $post, string $provider)
	{
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
	 * @param array|\WP_Post|null $post $post WP_Post or post ID passed by Rank Math.
	 * @return string Rewritten URL using headless domain.
	 */
	#[Filter('rank_math/indexing_api/delete_url',
		executeIf: static function (string $url): bool {
				return self::checkUrl($url);
				}
	)]
	public function RewriteDeleteUrl(string $url, array|\WP_Post|null $post)
	{
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
	 * @param SEO_Analyzer $analyzer.
	 * @return void
	 */
	#[Filter('seo_analysis/after_set_url',
		executeIf: static function (SEO_Analyzer $analyzer): bool {
					if (empty($analyzer) || !property_exists($analyzer, 'analyse_url')) {
					return false;
					}

				$original = $analyzer->analyse_url;
					if (empty($original)) {
					return false;
					}
				return true;
				}
	)]
	public function rewriteSeoAnalyzerInstanceUrl(SEO_Analyzer $analyzer): void
	{
		$original = $analyzer->analyse_url;
		$headless = rtrim(SharedUtils::headlessDomainRedirect(), '/');
		$parts = function_exists('wp_parse_url') ? wp_parse_url($original) : parse_url($original);
		$path = $parts['path'] ?? '/';
		$query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
		$analyzer->analyse_url = $headless . $path . $query;
	}
}