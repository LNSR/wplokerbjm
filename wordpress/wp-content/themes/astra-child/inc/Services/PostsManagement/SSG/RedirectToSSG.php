<?php

namespace AstraChild\Services\PostsManagement\SSG;

use AstraChild\Contracts\HooksInterface;
use AstraChild\Services\Utilities\SSG\LiteSpeedIntegration;

/**
 * SSG Service
 *
 * Serves static SSG versions of posts to non-logged-in users based on bot/human configuration
 */
class RedirectToSSG implements HooksInterface
{
    public function __construct(
        private \AstraChild\Services\Utilities\SSG\SSGUtilities $ssgUtilities
    )
    {
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
     * Check if the current visitor is a bot
     */
    private function isBot(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Exclude our own SSG bot from being treated as a bot
        $ssgBotUserAgents = apply_filters('ssg_excluded_user_agents', [
            'SSG-Bot/1.0',
            'Mozilla/5.0 (compatible; SSG-Bot/1.0)'
        ]);
        
        foreach ($ssgBotUserAgents as $ssgBot) {
            if (stripos($userAgent, $ssgBot) !== false) {
                return false; // Our SSG bot should not be redirected
            }
        }
        
        $botKeywords = apply_filters('ssg_bot_keywords', [
            'bot', 'crawler', 'spider', 'scraper', 'googlebot', 'bingbot', 'yahoo', 'duckduckbot',
            'facebookexternalhit', 'twitterbot', 'linkedinbot', 'whatsapp', 'slackbot', 'discordbot',
            'telegrambot', 'applebot', 'yandexbot', 'baiduspider', 'sogou', 'exabot', 'mj12bot',
            'dotbot', 'ahrefsbot', 'semrushbot', 'msnbot', 'slurp', 'pinterest', 'tumblr',
            'archive.org', 'wayback', 'uptimebot', 'pingdom', 'newrelic', 'datadog', 'cdn',
            'akamai', 'fastly', 'ia_archiver', 'quic', 'quicbot', 'quiccloud', 'lighthouse',
            'pagespeed', 'psi', 'webpagetest', 'gtmetrix', 'web.dev', 'crux', 'google-inspectiontool',
            'google-crawler', 'benchmark'
        ]);
        foreach ($botKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Serve SSG version based on configuration for bots or humans
     */
    public function serveSSG(): void
    {
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

        // Don't redirect logged-in users
        if (is_user_logged_in()) {
            return;
        }

        // Get configuration for who to serve SSG to
        $serveFor = get_option('ssg_serve_for', 'bots');
        $isBot = $this->isBot();

        // Check if we should serve based on visitor type
        if ($serveFor === 'bots' && !$isBot) {
            return;
        }
        if ($serveFor === 'humans' && $isBot) {
            return;
        }

        // Get the current post
        $post = get_post();
        if (!$post) {
            return;
        }

        // Get the SSG file path
        $ssgFilePath = $this->ssgUtilities->getSSGFilePath($post);

        // Check if SSG file exists
        if (file_exists($ssgFilePath)) {
            // Serve SSG content directly without redirect
            $ssgContent = file_get_contents($ssgFilePath);
            
            if ($ssgContent !== false) {
                // Log SSG serving
                LiteSpeedIntegration::logCoordination('Serving SSG to ' . ($isBot ? 'bot' : 'human'), ['post_id' => $post->ID, 'file' => basename($ssgFilePath)]);

                // Set SSG marker headers
                header('X-SSG: true');
                header('X-SSG-Source: static');
                header('X-SSG-Timestamp: ' . time());
                header('X-SSG-Version: 1.0');
                
                // Set proper headers
                header('Content-Type: text/html; charset=UTF-8');
                header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
                
                // Output the SSG content
                echo $ssgContent;
                exit;
            }
        }
    }
}
