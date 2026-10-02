<?php
namespace WPLokerBJM\Shared\Utilities;

use WPLokerBJM\Shared\Log\Logger;
class SharedUtils
{

    public static function isLocalhost(): bool
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $httpHost = $_SERVER['HTTP_HOST'] ?? '';
        $serverName = $_SERVER['SERVER_NAME'] ?? '';

        // Check exact localhost addresses
        static $exactLocalhost = [
        '127.0.0.1',
        '::1',
        'localhost',
        ];

        if (in_array($remoteAddr, $exactLocalhost)) {
            return true;
        }

        // Check for localhost in host/server name
        if (strpos($httpHost, 'localhost') !== false || strpos($serverName, 'localhost') !== false) {
            return true;
        }

        // Check for private network ranges (development environments)
        $privateRanges = ['192.168.', '10.0.', '172.'];

        foreach ($privateRanges as $range) {
            if (strpos($remoteAddr, $range) !== false || strpos($httpHost, $range) !== false || strpos($serverName, $range) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function doActivityAtBackground(callable $activity): void
    {
        try {
            if (defined('PHP_SAPI') && PHP_SAPI !== 'cli') {
                if (function_exists('litespeed_finish_request')) {
                    litespeed_finish_request();
                } elseif (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
            }
        } catch (\Exception $e) {
            Logger::error('SharedUtils::doActivityAtBackground error: ', $e->getMessage());
        } finally {
            $activity();
        }
    }

    public static function isWPCLI(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }

    public static function isDevelopment(): bool
    {
        return defined('WP_ENV') && WP_ENV === 'development';
    }

    /**
     * Return the headless frontend base URL depending on environment.
     * - development => https://localhost:5173
     * - production  => https://lokerbanjarmasin.my.id
     *
     * @return string
     */
    public static function headlessDomainRedirect(): string
    {
        return self::isDevelopment() ? 'https://localhost:5173' : 'https://lokerbanjarmasin.my.id';
    }

    /**
     * @return array{name: ?string, value: ?string}
     */
    public static function getWordpressAuthCookie(): array
    {
        $authCookieName = null;
        $authCookieValue = null;
        if (!empty($_COOKIE)) {
            foreach ($_COOKIE as $name => $val) {
                if (
                    str_starts_with($name, 'wordpress_sec_') ||
                    str_starts_with($name, 'wordpress_logged_in_')
                ) {
                    $authCookieName = $name;
                    $authCookieValue = \stripslashes_deep($val);
                    break;
                }
            }
        }
        return [
            'name' => $authCookieName,
            'value' => $authCookieValue
        ];
    }

    /**
     * Recursively filter out empty values from an array.
     *! make arrays returned values more compact by removing empty entries 
     *
     * @template T of array
     * @param T $dataArray The input array to filter.
     * @return T The filtered array with empty values removed.
     */
    public static function filterEmptyValues(array $dataArray): array
    {
        $filtered = [];
        foreach ($dataArray as $key => $value) {
            if (is_array($value)) {
                $filteredValue = self::filterEmptyValues($value);
                if (!empty($filteredValue)) {
                    $filtered[$key] = $filteredValue;
                }
            } elseif ($value !== null && $value !== '' && $value !== []) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}