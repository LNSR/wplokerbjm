<?php

namespace AstraChild\Services\Utilities\SSG\BotDetectionHelper;

/**
 * HeaderDetector
 *
 * Handles analysis of HTTP headers for bot detection
 */
class HeaderDetector
{
    /**
     * Evaluate suspicious or missing headers and return a small score (0..N).
     * This is intentionally conservative.
     */
    public function getSuspiciousHeaderScore(string $userAgent, string $acceptLanguage, string $referer): int
    {
        $score = 0;

        // Typical browsers send Accept-Language; absence is often suspicious
        if (empty($acceptLanguage)) {
            $score += 1;
        }

        // Many crawlers lack a referer; if UA looks like a real browser, don't count it
        if (empty($referer) && !empty($userAgent) && !preg_match('/Mozilla|Chrome|Safari|Firefox|Edge|Brave|SamsungBrowser|MiuiBrowser|XiaoMi|Opera|Vivaldi|YandexBrowser|UC|QQ|Sogou|360|Maxthon|Huawei|Vivo|Oppo|Baidu/i', $userAgent)) {
            $score += 1;
        }

        // Check for common proxy-related headers that often indicate scraping via proxies
        $proxyHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_VIA',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
            'HTTP_X_FORWARDED_HOST',
            'HTTP_X_FORWARDED_PROTO'
        ];
        foreach ($proxyHeaders as $h) {
            if (!empty($_SERVER[$h])) {
                $score += 1;
            }
        }

        // Headless and automation indicators in UA
        if (
            stripos($userAgent, 'HeadlessChrome') !== false ||
            stripos($userAgent, 'PhantomJS') !== false ||
            $this->hasWebDriverIndicators($userAgent)
        ) {
            $score += 1;
        }

        return $score;
    }

    /**
     * Additional header inspections and UA heuristics that are conservative.
     * Returns a small integer score (0..N).
     */
    public function getAdditionalHeaderScore(string $userAgent, string $acceptHeader, string $acceptEncoding): int
    {
        $score = 0;

        // Very generic or missing Accept header is suspicious for a real browser
        if (empty($acceptHeader) || trim($acceptHeader) === '*/*') {
            $score += 1;
        }

        // Most modern browsers advertise gzip/br/deflate; absence may indicate a simple HTTP client
        if (empty($acceptEncoding) || (stripos($acceptEncoding, 'gzip') === false && stripos($acceptEncoding, 'br') === false && stripos($acceptEncoding, 'deflate') === false)) {
            $score += 1;
        }

        // Modern browsers (Chrome/Firefox/Edge/Safari/Brave/Samsung/etc.) normally send Sec-Fetch-* headers and client hints.
        // If UA claims to be a modern browser but these headers are missing, that's slightly suspicious.
        $claimsModern = preg_match('/Chrome|Chromium|Firefox|Safari|Edge|Brave|SamsungBrowser|MiuiBrowser|XiaoMi|Opera|Vivaldi|YandexBrowser/i', $userAgent);
        $hasSecFetch = !empty($_SERVER['HTTP_SEC_FETCH_SITE']) || !empty($_SERVER['HTTP_SEC_FETCH_MODE']) || !empty($_SERVER['HTTP_SEC_FETCH_DEST']);
        $hasChUa = !empty($_SERVER['HTTP_SEC_CH_UA']) || !empty($_SERVER['HTTP_SEC_CH_UA_MOBILE']) || !empty($_SERVER['HTTP_SEC_CH_UA_PLATFORM']);

        if ($claimsModern && !$hasSecFetch) {
            $score += 1;
        }
        if (stripos($userAgent, 'Chrome') !== false && !$hasChUa) {
            // Chrome/Chromium variants commonly include client hints; absence is another small signal
            $score += 1;
        }

        // Very short or empty UA is suspicious
        if (empty($userAgent) || strlen($userAgent) < 15) {
            $score += 1;
        }

        return $score;
    }

    /**
     * Check for WebDriver or automation indicators in User-Agent or headers.
     */
    private function hasWebDriverIndicators(string $userAgent): bool
    {
        // Check UA for common automation strings
        $automationIndicators = [
            'webdriver',
            'selenium',
            'chrome-headless-shell',
            'electron',
            'nightmare',
            'casperjs',
            'mechanize',
            'scrapy'
        ];
        foreach ($automationIndicators as $indicator) {
            if (stripos($userAgent, $indicator) !== false) {
                return true;
            }
        }

        // Check for WebDriver-specific headers
        if (!empty($_SERVER['HTTP_X_WEBDRIVER']) || !empty($_SERVER['HTTP_WEBDRIVER'])) {
            return true;
        }

        return false;
    }
}