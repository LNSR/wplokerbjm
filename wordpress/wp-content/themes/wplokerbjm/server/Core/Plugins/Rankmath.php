<?php

namespace WPLokerBJM\Core\Plugins;

use WPLokerBJM\Shared\Cache\Cache;
use WPLokerBJM\Shared\Cache\CacheKey;
use WPLokerBJM\Shared\Log\Logger;
use WPlokerBJM\Core\Container\Attributes\Filter;

/**
 * Rank Math Integration Service
 * Extends RankMath plugin functionality
 * Handles Rank Math SEO plugin integrations including sitemap regeneration
 */
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
			self::$isActiveCache = function_exists('rank_math');
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
	public static function FixImageTypeOG($input)
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
}

