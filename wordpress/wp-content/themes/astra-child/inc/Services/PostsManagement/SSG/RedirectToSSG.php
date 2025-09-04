<?php

namespace AstraChild\Services\PostsManagement\SSG;

use AstraChild\Contracts\HooksInterface;
use AstraChild\Services\Utilities\SSG\LiteSpeedIntegration;
use AstraChild\Core\Cache;

/**
 * SSG Service
 *
 * Serves static SSG versions of posts to non-logged-in users based on bot/human configuration
 */
class RedirectToSSG implements HooksInterface
{
    public function __construct(
        private \AstraChild\Services\Utilities\SSG\SSGUtilities $ssgUtilities,
        private \AstraChild\Services\Utilities\SSG\BotDetection $botDetection
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
                LiteSpeedIntegration::logCoordination('Unable to read SSG file mtime', ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
                $currentMtime = time(); // fallback to avoid fatal comparisons
            }

            $ssgContent = false;

            if ($cached && is_array($cached) && isset($cached['mtime'], $cached['content']) && $cached['mtime'] >= $currentMtime) {
                $ssgContent = $cached['content'];
                LiteSpeedIntegration::logCoordination('Serving cached SSG to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
            } else {
                $ssgContent = @file_get_contents($ssgFilePath);
                if ($ssgContent !== false) {
                    Cache::set($cacheKey, ['content' => $ssgContent, 'mtime' => $currentMtime], expiration: 86400); // Cache for 1 day
                    LiteSpeedIntegration::logCoordination('Serving fresh SSG to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
                } else {
                    LiteSpeedIntegration::logCoordination('Failed to read SSG file', ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);
                }
            }

            return $ssgContent;
        } catch (\Exception $e) {
            error_log('RedirectToSSG::getSSGContent error for post ' . $post->ID . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Serve SSG version based on configuration for bots or humans
     */
    public function serveSSG(): void
    {
        try {
            // Check LiteSpeed coordination
            if (LiteSpeedIntegration::isActive()) {
                if (LiteSpeedIntegration::isCacheOperation()) {
                    LiteSpeedIntegration::logCoordination('Skipping SSG during LiteSpeed cache operation');
                    return;
                }
                if (LiteSpeedIntegration::shouldSkipDuringMaintenance()) {
                    LiteSpeedIntegration::logCoordination('Skipping SSG during LiteSpeed maintenance');
                    return;
                }
            }

            $serveFor = get_option('ssg_serve_for', 'bots');
            $isBot = $this->botDetection->isBot();

            // Always skip SSG for logged-in users to prevent false positives from bot detection
            if (is_user_logged_in()) {
                LiteSpeedIntegration::logCoordination('Skipping SSG for logged-in user', ['is_bot' => $isBot]);
                return;
            }

            // Skip SSG for ESI/fragment requests to avoid returning full static pages
            if (LiteSpeedIntegration::isEsiRequest()) {
                LiteSpeedIntegration::logCoordination('Skipping SSG for ESI/fragment request');
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

            $ssgFilePath = $this->ssgUtilities->getSSGFilePath($post);

            if (file_exists($ssgFilePath)) {
                $ssgContent = $this->getSSGContent($post, $ssgFilePath, $isBot);

                if ($ssgContent !== false) {
                    // Centralized header emission (server-level caching is preferred)
                    LiteSpeedIntegration::sendSSGResponseHeaders((int) $post->ID, $isBot, strlen($ssgContent));

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
