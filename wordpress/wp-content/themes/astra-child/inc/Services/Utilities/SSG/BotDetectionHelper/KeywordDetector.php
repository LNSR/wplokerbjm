<?php

namespace AstraChild\Services\Utilities\SSG\BotDetectionHelper;

/**
 * KeywordDetector
 *
 * Handles user agent keyword detection for bot identification
 */
class KeywordDetector
{
    /**
     * Check user agent for bot keywords and return a score
     */
    public function getKeywordScore(string $userAgent): int
    {
        $score = 0;

        $botKeywords = apply_filters('ssg_bot_keywords', [
            'bot',
            'crawler',
            'spider',
            'scraper',
            'googlebot',
            'bingbot',
            'yahoo',
            'duckduckbot',
            'facebookexternalhit',
            'twitterbot',
            'linkedinbot',
            'whatsapp',
            'slackbot',
            'discordbot',
            'telegrambot',
            'applebot',
            'yandexbot',
            'baiduspider',
            'sogou',
            'exabot',
            'mj12bot',
            'dotbot',
            'ahrefsbot',
            'semrushbot',
            'msnbot',
            'slurp',
            'pinterest',
            'tumblr',
            'archive.org',
            'wayback',
            'uptimebot',
            'pingdom',
            'newrelic',
            'datadog',
            'cdn',
            'akamai',
            'fastly',
            'ia_archiver',
            'quic',
            'quicbot',
            'quiccloud',
            'lighthouse',
            'pagespeed',
            'psi',
            'webpagetest',
            'gtmetrix',
            'web.dev',
            'crux',
            'google-inspectiontool',
            'google-crawler',
            'benchmark',
            'rankmath',
            'rank-math',
            'rank math',
            'petalbot',
            'bytespider',
            'claudebot',
            'gptbot',
            'perplexitybot',
            'coherebot',
            'diffbot',
            'rogerbot',
            'archivebot',
            'crawl',
            'fetch',
            'monitor',
            'scan',
            'index',
            'facebookbot',
            'meta-webindexer',
            'meta-externalads',
            'meta-externalagent',
            'meta-externalfetcher',
            'facebookexternalhit',
            'facebookcatalog',
            'pinterestbot',
            'pinterest',
            'redditbot',
            'curl',
            'wget',
            'httpie',
            'python-requests',
            'python-urllib',
            'urllib3',
            'httpx',
            'node-fetch',
            'axios',
            'okhttp',
            'go-http-client',
            'libwww-perl',
            'perl',
            'php-curl',
            'apache-httpclient',
            'puppeteer',
            'playwright',
            'selenium',
            'webdriver',
            'chrome-headless-shell',
            'electron',
            'nightmare',
            'casperjs',
            'mechanize',
            'scrapy',
            'screaming frog',
            'sitebulb',
            'majestic',
            'seokicks',
            'yandeximages',
            'shopifybot',
            'hubspot',
            'uptimerobot',
            'dareboost',
            'uptrends',
            'sitespeed',
            'yellowlab',
            'calibre',
            'speedcurve',
            'webvitals',
            'performance',
            'audit',
            'seo',
            'core-web-vitals',
            'debugbear'
        ]);

        foreach ($botKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * Check if the current visitor is our SSG bot generation
     */
    public static function isSsgBotGeneration(): array
    {
        return apply_filters('ssg_excluded_user_agents', [
            'SSG-Bot/1.0',
            'Mozilla/5.0 (compatible; SSG-Bot/1.0)'
        ]);
    }
}