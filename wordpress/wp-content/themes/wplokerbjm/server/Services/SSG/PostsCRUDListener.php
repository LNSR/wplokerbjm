<?php

namespace WPLokerBJM\Services\SSG;

use WPLokerBJM\Services\Utilities\SSG\Integrations\RankMathIntegration;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Services\Utilities\SSG\{SSGUtilities, BotDetection};
use WPLokerBJM\Services\Webhooks\TriggerBuildSSG;
use WPLokerBJM\Core\Container\Attributes\Action;

/**
 * Listens to post CRUD events and notifies the SSG trigger service.
 *
 * Handles WordPress post creation, updates, and deletions by triggering SSG builds
 * and coordinating with caching systems. Includes debouncing to prevent excessive
 * build triggers from rapid successive saves.
 */
class PostsCRUDListener
{
	public function __construct(
		private TriggerBuildSSG $triggerBuildSSG,
		private BotDetection $botDetection
	) {
	}

	/**
	 * Check if we should debounce the post update to prevent rapid successive triggers
	 *
	 * This method implements a debouncing mechanism to avoid triggering SSG builds
	 * too frequently for the same post. It uses WordPress transients to track recent
	 * triggers and applies a fixed debounce timing.
	 *
	 * @param int $post_id The ID of the post being updated
	 * @return bool True if the update should be debounced (skipped), false if it should proceed
	 */
	private function shouldDebouncePost(int $post_id): bool
	{
		$cacheKey = CacheKey::SSG_POST_DEBOUNCE_PREFIX . $post_id;
		$lastTrigger = Cache::get($cacheKey);

		if ($lastTrigger !== false) {
			Logger::info('SSG', "Skipping duplicate trigger for post {$post_id} within debounce window");
			return true;
		}

		// Set debounce transient before triggering
		$debounceSeconds = 60; // Fixed 1 minute debounce
		Cache::set($cacheKey, time(), $debounceSeconds);

		return false;
	}

	#[Action('save_post', 10, 3)]
	public function onSavePost(int $post_id, \WP_Post $post, bool $update): void
	{
		try {
			// Skip autosaves and revisions
			if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
				return;
			}

			// Skip if this is an SSG bot request
			if (SharedUtils::isSsgBotRequest()) {
				$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
				Logger::info('SSG', "Skipping trigger for SSG bot request - Post ID: {$post_id}, User Agent: {$userAgent}");
				return;
			}

			// Optionally skip non-public posts
			if (!in_array($post->post_status, ['publish', 'private', 'future'], true)) {
				// If you want to only handle published posts, require 'publish' here
				return;
			}

			// Check if we recently triggered a build for this post to prevent rapid successive triggers
			if ($this->shouldDebouncePost($post_id)) {
				return;
			}

			$permalink = get_permalink($post_id);
			$home = home_url('/');

			$paths = SSGUtilities::collectPathsFromPost($post_id, $permalink, $home);

			// Trigger GitHub Actions for updates/creations
			$this->triggerBuildSSG->trigger($paths, $update ? 'post_updated' : 'post_created');

			// Force Rank Math sitemap regeneration for immediate updates
			RankMathIntegration::regenerateSitemap($post_id, $post);
		} catch (\Exception $e) {
			Logger::error('SSG', 'PostsCRUDListener::onSavePost error for post ' . $post_id . ': ' . $e->getMessage());
		}
	}

	/**
	 * Handle post deletion before it's actually deleted
	 */
	#[Action('before_delete_post', 1, 1)]
	public function onBeforeDeletePost(int $post_id): void
	{
		try {
			SSGUtilities::deleteSSGFile($post_id, 'post_deleted');
			RankMathIntegration::regenerateSitemapOnDelete($post_id);
		} catch (\Exception $e) {
			Logger::error('SSG', 'PostsCRUDListener::onBeforeDeletePost error for post ' . $post_id . ': ' . $e->getMessage());
		}
	}

	/**
	 * Handle post trashing
	 */
	#[Action('wp_trash_post', 1, 1)]
	public function onTrashPost(int $post_id): void
	{
		try {
			SSGUtilities::deleteSSGFile($post_id, 'post_trashed');
			RankMathIntegration::regenerateSitemapOnDelete($post_id);
		} catch (\Exception $e) {
			Logger::error('SSG', 'PostsCRUDListener::onTrashPost error for post ' . $post_id . ': ' . $e->getMessage());
		}
	}
}