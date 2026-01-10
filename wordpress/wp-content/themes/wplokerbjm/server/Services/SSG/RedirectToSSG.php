<?php

namespace WPLokerBJM\Services\SSG;

use WPLokerBJM\Services\Utilities\SSG\SSGUtilities;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\Action;

/**
 * SSG Service
 *
 * Serves static SSG versions of posts to non-logged-in users based on bot/human configuration
 */
class RedirectToSSG
{
	public function __construct(
		private \WPLokerBJM\Services\Utilities\SSG\BotDetection $botDetection,
		private \WPLokerBJM\Services\Webhooks\TriggerBuildSSG $triggerBuildSSG,
	) {
	}

	const COOKIE_NAME = '_lscache_vary_wplokerbjm_visitor_type';

	#[Action('template_redirect', 0)]
	public function serveSSG(): void
	{
		try {
			$isBot = $this->botDetection->isBot();

			if (is_user_logged_in()) {
				return;
			}

			// Self-heal from cache poisoning: if cookie indicates human, don't serve SSG
			if (isset($_COOKIE[self::COOKIE_NAME]) && $_COOKIE[self::COOKIE_NAME] === 'human') {
				return;
			}

			// Log browser information for SSG request
			$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
			$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
			$acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
			$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
			$referer = $_SERVER['HTTP_REFERER'] ?? '';
			Logger::info('SSG', 'Browser info for SSG request', [
				'user_agent' => $userAgent,
				'ip' => $remoteAddr,
				'accept_language' => $acceptLanguage,
				'accept' => $acceptHeader,
				'referer' => $referer,
				'is_bot' => $isBot,
			]);

			if (!$isBot) {
				return; // Serve SSG only to bots
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
			Logger::error('SSG', 'RedirectToSSG::serveSSG error: ' . $e->getMessage());
		}
	}

	/**
	 * Build global custom headers so it distinguishes between bot and human visitors
	 * @source https://docs.litespeedtech.com/lscache/devguide/advanced/#cache-varies for Vary
	 */
	#[Action('send_headers', 10)]
	public function buildHeaders()
	{

		$isBot = $this->botDetection->isBot();

		$isSSGbot = SharedUtils::isSsgBotRequest();
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
			Logger::info('SSG', 'Visitor cookie set', ['cookie' => $cookieName, 'value' => $visitorType]);
		}
		$isSSGbot ? header('Vary: Cookie,User-Agent,Accept-Encoding') : header('Vary: Cookie,Accept-Encoding');
	}

	/**
	 * Set exclusive SSG headers when served
	 */
	public function exclusiveSSGHeaders($post, $ssgContent, $contentLength, ?string $extraHeaders = null): void
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
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', get_the_modified_time('U', $post->ID)));
		header('Server-Timing: ssg-serve;dur=' . (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000);
		$sitemap_url = home_url('/sitemap_index.xml');
        header('Link: <' . esc_url($sitemap_url) . '>; rel="sitemap"');
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
			$cacheKey = CacheKey::SSG_CONTENT_PREFIX . $post->ID . '_' . $visitorType;
			$cached = Cache::get($cacheKey);
			$ssgContent = false;

			$currentMtime = @filemtime($ssgFilePath);
			if ($currentMtime === false) {
				Logger::info('SSG', 'Unable to read SSG file mtime', ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				$currentMtime = time(); // fallback to avoid fatal comparisons
			}

			// Check if SSG file exists and is readable
			if (!is_readable($ssgFilePath)) {
				return false;
			}

			if ($cached && is_array($cached) && isset($cached['mtime'], $cached['content']) && $cached['mtime'] == $currentMtime) {
				$ssgContent = $cached['content'];
				Logger::info('SSG', 'Serving cached SSG from cache to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
			} else {
				$ssgContent = @file_get_contents($ssgFilePath);
				if ($ssgContent !== false) {
					Cache::set($cacheKey, ['content' => $ssgContent, 'mtime' => $currentMtime], 0); // No expiration, rely on mtime check
					Logger::info('SSG', 'Serving fresh SSG from disk to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				} else {
					Logger::info('SSG', 'Failed to read SSG file from disk', ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
				}
			}

			return $ssgContent;
		} catch (\Exception $e) {
			Logger::error('SSG', 'RedirectToSSG::getSSGContent error for post ' . $post->ID . ': ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Assurance self-healing: set cookie back to human on user activity to mitigate cache poisoning.
	 *! sometime cache poisoning occurs during a human visits and cookie trapped as bot.
	 *! make sure this script is generated by SSG bot if modifying code.
	 * Rant: months experimenting with various cache poisoning self-heal methods, this is the most reliable way; Seriously GDI!
	 */
	#[Action('wp_footer', 10)]
	public function setCookieToHuman(): void
	{
		if (is_user_logged_in()) {
			return;
		}

		?>

		<script id="self-heal-cookie">
			(() => {
				const scriptElement = document.getElementById('self-heal-cookie');
				let hasRun = false;
				const cookieName = '<?= self::COOKIE_NAME; ?>';
				const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click', 'touchmove', 'wheel'];

				const cleanup = () => {
					events.forEach(event => document.removeEventListener(event, runSelfHeal));
					scriptElement?.remove();
				};

				const runSelfHeal = () => {
					if (hasRun) return;
					hasRun = true;

					if (document.cookie.includes(cookieName + '=ssg_bot')) {
						console.log('SSG generation bot detected, skipping self-heal to avoid interference');
						return;
					}
					if (document.cookie.includes(cookieName + '=human')) {
						console.log('Cookie already set to human, no action needed');
						cleanup();
						return;
					}
					document.cookie = cookieName + '=human; path=/; max-age=86400;' + (location.protocol === 'https:' ? ' Secure;' : '') + ' SameSite=Lax';
					console.log('Set cookie to human for SSG self-healing');
					location.replace(location.href);
				};

				events.forEach(event => document.addEventListener(event, runSelfHeal, { once: true, passive: true }));

				setTimeout(() => {
					if (!hasRun) {
						console.log('No activity detected, skipping self-heal');
						cleanup();
					}
				}, 10000);
			})()
		</script>
		<?php
	}
}