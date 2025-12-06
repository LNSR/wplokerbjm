<?php

namespace WPLokerBJM\Services\PostsManagement\SSG;

use WPLokerBJM\Contracts\HooksInterface;
use WPLokerBJM\Services\Utilities\SSG\Integrations\{SSGIntegration, LiteSpeedIntegration};
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
		private \WPLokerBJM\Services\Utilities\SSG\BotDetection $botDetection,
		private \WPLokerBJM\Services\Webhooks\TriggerBuildSSG $triggerBuildSSG
	) {
	}

	const COOKIE_NAME = '_lscache_vary_wplokerbjm_visitor_type';

	public function registerActions(): void
	{
		$priorities = LiteSpeedIntegration::getHookPriorities();
		add_action('template_redirect', [$this, 'serveSSG'], $priorities['ssg_before_litespeed']);
		add_action('send_headers', [$this, 'buildHeaders'], $priorities['ssg_after_litespeed']);
		add_action('wp_footer', [$this, 'setCookieBackToHuman']);
	}

	public function registerFilters(): void
	{
		// No filters to register
	}

	public function serveSSG(): void
	{
		try {
			$serveFor = get_option('ssg_serve_for', 'bots');
			$isBot = $this->botDetection->isBot();

			if (is_user_logged_in()) {
				return;
			}

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
				'is_bot' => $isBot,
			]);

			// Self-heal from cache poisoning: if cookie indicates human, don't serve SSG
			if (isset($_COOKIE[self::COOKIE_NAME]) && $_COOKIE[self::COOKIE_NAME] === 'human') {
				return;
			}

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

			$ssgContent = false;
			if (file_exists($ssgFilePath)) {
				$ssgContent = $this->getSSGContent($post, $ssgFilePath, $isBot);

				if ($ssgContent !== false) {
					$this->exclusiveSSGHeaders($post, $ssgContent, \strlen($ssgContent));
					echo $ssgContent;
					exit;
				}
			}

			// For home page, if SSG not found or not cached, dispatch build
			if (is_front_page() && $ssgContent === false) {
				$this->triggerBuildSSG->trigger(['/'], 'home_page_missing_ssg', false);
			}
		} catch (\Exception $e) {
			error_log('RedirectToSSG::serveSSG error: ' . $e->getMessage());
		}
	}

	/**
	 * Build global custom headers so it distinguishes between bot and human visitors
	 * @source https://docs.litespeedtech.com/lscache/devguide/advanced/#cache-varies for Vary
	 */
	public function buildHeaders()
	{

		$isBot = $this->botDetection->isBot();
		
		$UserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$isSSGbot = in_array($UserAgent, $this->botDetection::isSsgBotGeneration(), true);
		$visitorType = $isSSGbot ? 'ssg_bot' : ($isBot ? 'bot' : 'human');

		// LiteSpeed expects: X-LiteSpeed-Vary: cookie=my_cookie_name or header=...
		$cookieName = self::COOKIE_NAME;

		if (!isset($_COOKIE[$cookieName]) || $_COOKIE[$cookieName] !== $visitorType) {
			setcookie(
				$cookieName,
				$visitorType,
				[
					'expires' => time() + 86400, // 1 day
					'path' => '/',
					'secure' => is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				]
			);
			$_COOKIE[$cookieName] = $visitorType;
			header('X-LiteSpeed-Vary: cookie=' . $cookieName . ',value=' . $visitorType);
			SSGIntegration::logCoordination('Visitor cookie set', ['cookie' => $cookieName, 'value' => $visitorType]);
		}
		$isSSGbot ? header('Vary: Cookie,User-Agent') : header('Vary: Cookie');
	}

	/**
	 * Set exclusive SSG headers when served
	 */
	public function exclusiveSSGHeaders($post, $ssgContent, $contentLength)
	{
		$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
		$etag = '"' . md5($post->ID . '-' . $ssgContent) . '"';

		if ($ifNoneMatch === $etag) {
			header('HTTP/1.1 304 Not Modified');
			header('Etag: ' . $etag);
			exit;
		}
		header('Etag: ' . $etag);
		header('X-SSG-Served: true');
		header('X-Litespeed-Cache-Control: public,max-age=3600');
		header('X-SSG-Post-ID: ' . $post->ID);
		header('Content-Length: ' . $contentLength);
	}

	/**
	 * Get SSG content with caching logic
	 *
	 * @param mixed $post The post object
	 * @param string $ssgFilePath The path to the SSG file
	 * @param bool $isBot Whether the visitor is a bot
	 * @return string|false The SSG content or false on failure
	 */
	private function getSSGContent($post, $ssgFilePath, $isBot): mixed
	{
		try {
			$visitorType = $isBot ? 'bot' : 'human';
			$cacheKey = 'ssg_content_' . $post->ID . '_' . $visitorType;
			$cached = Cache::get($cacheKey);
			$ssgContent = false;

			$currentMtime = @filemtime($ssgFilePath);
			if ($currentMtime === false) {
				SSGIntegration::logCoordination('Unable to read SSG file mtime', ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				$currentMtime = time(); // fallback to avoid fatal comparisons
			}

			// Check if SSG file exists and is readable
			if (!is_readable($ssgFilePath)) {
				return false;
			}

			if ($cached && is_array($cached) && isset($cached['mtime'], $cached['content']) && $cached['mtime'] == $currentMtime) {
				$ssgContent = $cached['content'];
				SSGIntegration::logCoordination('Serving cached SSG from cache to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
			} else {
				$ssgContent = @file_get_contents($ssgFilePath);
				if ($ssgContent !== false) {
					Cache::set($cacheKey, ['content' => $ssgContent, 'mtime' => $currentMtime], 0); // No expiration, rely on mtime check
					SSGIntegration::logCoordination('Serving fresh SSG from disk to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				} else {
					SSGIntegration::logCoordination('Failed to read SSG file from disk', ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				}
			}

			return $ssgContent;
		} catch (\Exception $e) {
			error_log('RedirectToSSG::getSSGContent error for post ' . $post->ID . ': ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Assurance self-healing: set cookie back to human on user activity to mitigate cache poisoning.
	 *! sometime cache poisoning occurs during a human visits and cookie trapped as bot.
	 *! make sure this script is generated by SSG bot if modifying code.
	 * Rant: months experimenting with various cache poisoning self-heal methods, this is the most reliable way; Seriously GDI!
	 */
	public function setCookieBackToHuman(): void
	{
		$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$isSSGbot = in_array($useragent, $this->botDetection::isSsgBotGeneration(), true);

		if (is_user_logged_in()) {
			return;
		}

		?>

		<script id="self-heal-cookie" data-no-optimize="1">
			(() => {
				const ssgGenerationBot = <?= $isSSGbot ? 'true' : 'false' ?>;
				const scriptElement = document.getElementById('self-heal-cookie');
				let hasRun = false;

				function runSelfHeal() {
					if (hasRun) return;
					hasRun = true;

					const cookieName = '<?= self::COOKIE_NAME; ?>';
					if (document.cookie.includes(cookieName + '=human')) {
						if (!!ssgGenerationBot) return;
						scriptElement?.remove();
						return;
					}
					document.cookie = cookieName + '=human; path=/; max-age=86400; <?= is_ssl() ? 'Secure; ' : ''; ?>SameSite=Lax';
					console.log('Set cookie to human for SSG self-healing');
					location.replace(location.href);
				}

				// Listen for human activity events
				const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
				events.forEach(event => {
					document.addEventListener(event, runSelfHeal, { once: true, passive: true });
				});

				setTimeout(() => {
					if (!hasRun) {
						console.log('No activity detected, skipping self-heal');
						scriptElement?.remove();
					}
				}, 10000);
			})()
		</script>
		<?php
	}
}
