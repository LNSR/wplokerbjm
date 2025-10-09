<?php

namespace WPLokerBJM\Services\PostsManagement\SSG;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Services\Utilities\SSG\Integrations\SSGIntegration;
use WPLokerBJM\Services\Utilities\SSG\Integrations\LiteSpeedIntegration;
use WPLokerBJM\Services\Utilities\SSG\SSGUtilities;
use WPLokerBJM\Core\Cache;

/**
 * SSG Service
 *
 * Serves static SSG versions of posts to non-logged-in users based on bot/human configuration
 */
class RedirectToSSG implements HooksInterface
{
	public function __construct(
		private \WPLokerBJM\Services\Utilities\SSG\BotDetection $botDetection
	) {
	}

	public function registerActions(): void
	{
		$priorities = LiteSpeedIntegration::getHookPriorities();
		add_action('template_redirect', [$this, 'serveSSG'], $priorities['ssg_before_litespeed']);
	}

	public function registerFilters(): void
	{
		// No filters to register
	}

	/**
	 * Get SSG content with caching logic
	 *
	 * @param mixed $post The post object
	 * @param string $ssgFilePath The path to the SSG file
	 * @param bool $isBot Whether the visitor is a bot
	 * @return string|false The SSG content or false on failure
	 */
	private function getSSGContent($post, $ssgFilePath, $isBot)
	{
		try {
			$cacheKey = 'ssg_content_' . $post->ID;
			$cached = Cache::get($cacheKey);

			$currentMtime = @filemtime($ssgFilePath);
			if ($currentMtime === false) {
				SSGIntegration::logCoordination('Unable to read SSG file mtime', ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				$currentMtime = time(); // fallback to avoid fatal comparisons
			}

			// Check if SSG file exists and is readable
			if (is_readable($ssgFilePath)) {
				$mtime = filemtime($ssgFilePath);
				if ($mtime !== false) {
					$age = time() - $mtime;
					if ($age < 86400) { // 1 day
						SSGIntegration::logCoordination('Serving cached SSG to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
					} else {
						SSGIntegration::logCoordination('Serving fresh SSG to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
					}
				} else {
					SSGIntegration::logCoordination('Failed to read SSG file', ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				}
			} else {
				return false;
			}
			$ssgContent = false;

			if ($cached && is_array($cached) && isset($cached['mtime'], $cached['content']) && $cached['mtime'] >= $currentMtime) {
				$ssgContent = $cached['content'];
				SSGIntegration::logCoordination('Serving cached SSG to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
			} else {
				$ssgContent = @file_get_contents($ssgFilePath);
				if ($ssgContent !== false) {
					Cache::set($cacheKey, ['content' => $ssgContent, 'mtime' => $currentMtime], expiration: 86400); // Cache for 1 day
					SSGIntegration::logCoordination('Serving fresh SSG to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				} else {
					SSGIntegration::logCoordination('Failed to read SSG file', ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				}
			}

			return $ssgContent;
		} catch (\Exception $e) {
			error_log('RedirectToSSG::getSSGContent error for post ' . $post->ID . ': ' . $e->getMessage());
			return false;
		}
	}

	public function serveSSG(): void
	{
		try {
			$serveFor = get_option('ssg_serve_for', 'bots');
			$isBot = $this->botDetection->isBot();

			// Log browser information for SSG request
			$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
			$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
			$acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
			$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
			$referer = $_SERVER['HTTP_REFERER'] ?? '';
			SSGIntegration::logCoordination('Browser info for SSG request', [
				'user_agent' => $userAgent,
				'ip' => $remoteAddr,
				'accept_language' => $acceptLanguage,
				'accept' => $acceptHeader,
				'referer' => $referer,
				'is_bot' => $isBot
			]);

			// Get configuration for who to serve SSG to
			// Check if we should serve based on visitor type
			if ($serveFor === 'bots' && !$isBot) {
				return;
			}
			if ($serveFor === 'humans' && $isBot) {
				return;
			}

			$post = get_post();
			if (!$post) {
				return;
			}

			$ssgFilePath = SSGUtilities::getSSGFilePath($post);

			if (file_exists($ssgFilePath)) {
				$ssgContent = $this->getSSGContent($post, $ssgFilePath, $isBot);

				if ($ssgContent !== false) {
					header('X-SSG: true');
					header('X-SSG-Source: static');
					header('X-SSG-Timestamp: ' . time());
					header('X-SSG-Version: 1.0');
					header('X-SSG-Visitor-Type: ' . ($isBot ? 'bot' : 'human'));
					header('Content-Type: text/html; charset=UTF-8');
					header('Cache-Control: public, max-age=120');

					// Output the SSG content
					echo $ssgContent;
					exit;
				}
			}
		} catch (\Exception $e) {
			error_log('RedirectToSSG::serveSSG error: ' . $e->getMessage());
		}
	}
}
