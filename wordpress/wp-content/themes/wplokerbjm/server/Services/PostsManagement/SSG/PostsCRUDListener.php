<?php

namespace WPLokerBJM\Services\PostsManagement\SSG;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Services\Utilities\SSG\Integrations\{SSGIntegration, LiteSpeedIntegration, RankMathIntegration};
use WPLokerBJM\Core\Cache;
use WPLokerBJM\Services\Utilities\SSG\{SSGUtilities, BotDetection};
use WPLokerBJM\Services\Webhooks\TriggerBuildSSG;

/**
 * SSG Event Listeners for WordPress Posts and LiteSpeed Cache Events
 *
 * This file contains two event listener classes:
 * - PostsCRUDListener: Handles post create/update/delete events
 * - LiteSpeedEventListener: Handles LiteSpeed cache purge events
 *
 * Both classes use the BotRequestDetectionTrait to detect and skip SSG bot requests.
 */

trait BotRequestDetectionTrait
{
	/**
	 * Check if the current request is from our SSG bot
	 */
	function isSsgBotRequest(): bool
	{
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$ssgBotUAs = BotDetection::isSsgBotGeneration();
		foreach ($ssgBotUAs as $ua) {
			if (stripos($userAgent, $ua) !== false) {
				return true;
			}
		}
		return false;
	}
}

/**
 * Listens to post CRUD events and notifies the SSG trigger service.
 *
 * Handles WordPress post creation, updates, and deletions by triggering SSG builds
 * and coordinating with caching systems. Includes debouncing to prevent excessive
 * build triggers from rapid successive saves.
 */
class PostsCRUDListener implements HooksInterface
{
	use BotRequestDetectionTrait;
	public function __construct(
		private TriggerBuildSSG $triggerBuildSSG,
		private BotDetection $botDetection
	) {
	}

	public function registerActions(): void
	{
		// Use priority 10; adjust as needed (LiteSpeed typically uses 10-20)
		add_action('save_post', [$this, 'onSavePost'], 10, 3);

		// Hook into post deletion with high priority to run before other cleanup
		add_action('before_delete_post', [$this, 'onBeforeDeletePost'], 1, 1);
		add_action('wp_trash_post', [$this, 'onTrashPost'], 1, 1);
	}

	public function registerFilters(): void
	{
		// No filters
	}

	/**
	 * Check if we should debounce the post update to prevent rapid successive triggers
	 *
	 * This method implements a debouncing mechanism to avoid triggering SSG builds
	 * too frequently for the same post. It uses WordPress transients to track recent
	 * triggers and applies different debounce timings based on whether LiteSpeed Cache
	 * is active (longer debounce for coordination) or not.
	 *
	 * @param int $post_id The ID of the post being updated
	 * @return bool True if the update should be debounced (skipped), false if it should proceed
	 */
	private function shouldDebouncePost(int $post_id): bool
	{
		$cacheKey = "ssg_post_debounce_{$post_id}";
		$lastTrigger = Cache::get($cacheKey);

		if ($lastTrigger !== false) {
			error_log("SSG PostsCRUDListener: Skipping duplicate trigger for post {$post_id} within debounce window");
			return true;
		}

		// Set debounce transient before triggering
		$debounceTiming = LiteSpeedIntegration::getDebounceTiming();
		$debounceSeconds = LiteSpeedIntegration::isActive() ? $debounceTiming['litespeed_coordination'] : $debounceTiming['normal_operation'];
		Cache::set($cacheKey, time(), $debounceSeconds);

		return false;
	}

	public function onSavePost(int $post_id, \WP_Post $post, bool $update): void
	{
		try {
			// Skip autosaves and revisions
			if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
				return;
			}

			// Skip if this is an SSG bot request
			if ($this->isSsgBotRequest()) {
				$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
				error_log("SSG PostsCRUDListener: Skipping trigger for SSG bot request - Post ID: {$post_id}, User Agent: {$userAgent}");
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
			error_log('PostsCRUDListener::onSavePost error for post ' . $post_id . ': ' . $e->getMessage());
		}
	}

	/**
	 * Handle post deletion before it's actually deleted
	 */
	public function onBeforeDeletePost(int $post_id): void
	{
		try {
			SSGUtilities::deleteSSGFile($post_id, 'post_deleted');
			RankMathIntegration::regenerateSitemapOnDelete($post_id);
		} catch (\Exception $e) {
			error_log('PostsCRUDListener::onBeforeDeletePost error for post ' . $post_id . ': ' . $e->getMessage());
		}
	}

	/**
	 * Handle post trashing
	 */
	public function onTrashPost(int $post_id): void
	{
		try {
			SSGUtilities::deleteSSGFile($post_id, 'post_trashed');
			RankMathIntegration::regenerateSitemapOnDelete($post_id);
		} catch (\Exception $e) {
			error_log('PostsCRUDListener::onTrashPost error for post ' . $post_id . ': ' . $e->getMessage());
		}
	}
}

/**
 * Listens to LiteSpeed cache purge events and coordinates SSG updates.
 *
 * Handles LiteSpeed Cache purge events by triggering delayed SSG rebuilds
 * to ensure cache operations complete before static generation begins.
 */
class LiteSpeedEventListener implements HooksInterface
{
	use BotRequestDetectionTrait;
	public function __construct(
		private TriggerBuildSSG $triggerBuildSSG,
		private BotDetection $botDetection
	) {
	}

	public function registerActions(): void
	{
		if (LiteSpeedIntegration::isActive()) {
			$priorities = LiteSpeedIntegration::getHookPriorities();
			add_action('litespeed_purge_post', [$this, 'onLiteSpeedPurgePost'], $priorities['ssg_after_litespeed'], 1);
			add_action('litespeed_purge_all', [$this, 'onLiteSpeedPurgeAll'], $priorities['ssg_after_litespeed']);
		}

		// Register scheduled event handler
		add_action('ssg_delayed_post_update', [$this, 'handleDelayedPostUpdate'], 10, 1);
	}

	public function registerFilters(): void
	{
		// No filters
	}

	/**
	 * Handle LiteSpeed post purge events
	 */
	public function onLiteSpeedPurgePost(int $post_id): void
	{
		try {
			// Skip if this is an SSG bot request
			if ($this->isSsgBotRequest()) {
				return;
			}

			// Set transient to mark recent LiteSpeed purge activity
			Cache::set('litespeed_recent_purge', time(), 300); // 5 minutes

			SSGIntegration::logCoordination("LiteSpeed purged post, coordinating SSG update", ['post_id' => $post_id]);

			// Add a small delay to ensure LiteSpeed operations complete first
			wp_schedule_single_event(time() + 2, 'ssg_delayed_post_update', [$post_id]);
		} catch (\Exception $e) {
			error_log('LiteSpeedEventListener::onLiteSpeedPurgePost error for post ' . $post_id . ': ' . $e->getMessage());
		}
	}

	/**
	 * Handle LiteSpeed full cache purge
	 */
	public function onLiteSpeedPurgeAll(): void
	{
		try {
			// Skip if this is an SSG bot request
			if ($this->isSsgBotRequest()) {
				return;
			}

			// Set transient to mark recent LiteSpeed purge activity
			Cache::set('litespeed_recent_purge', time(), 300); // 5 minutes

			SSGIntegration::logCoordination("LiteSpeed purged all cache, triggering full SSG rebuild");

			// Trigger full site rebuild
			$this->triggerBuildSSG->trigger([home_url('/')], 'litespeed_full_purge');
		} catch (\Exception $e) {
			error_log('LiteSpeedEventListener::onLiteSpeedPurgeAll error: ' . $e->getMessage());
		}
	}

	/**
	 * Handle delayed post update from LiteSpeed purge events
	 */
	public function handleDelayedPostUpdate(int $post_id): void
	{
		try {
			// Skip if this is an SSG bot request
			if ($this->isSsgBotRequest()) {
				return;
			}

			// Check if post still exists
			$post = get_post($post_id);
			if (!$post) {
				return;
			}

			$permalink = get_permalink($post_id);
			$home = home_url('/');

			$paths = SSGUtilities::collectPathsFromPost($post_id, $permalink, $home);

			// Trigger GitHub Actions for the delayed update
			$this->triggerBuildSSG->trigger($paths, 'litespeed_post_purge');

			// Force Rank Math sitemap regeneration
			RankMathIntegration::regenerateSitemap($post_id, $post);
		} catch (\Exception $e) {
			error_log('LiteSpeedEventListener::handleDelayedPostUpdate error for post ' . $post_id . ': ' . $e->getMessage());
		}
	}
}