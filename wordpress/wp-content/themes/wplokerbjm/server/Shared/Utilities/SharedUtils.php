<?php
namespace WPLokerBJM\Shared\Utilities;
class SharedUtils
{
    public static function isLocalhost(): bool
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $httpHost = $_SERVER['HTTP_HOST'] ?? '';
        $serverName = $_SERVER['SERVER_NAME'] ?? '';

        // Check exact localhost addresses
        $exactLocalhost = [
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

    public static function isSsgBotRequest(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $ssgBotUAs = apply_filters('ssg_excluded_user_agents', [
            'SSG-Bot/1.0',
            'Mozilla/5.0 (compatible; SSG-Bot/1.0)',
        ]);

        foreach ($ssgBotUAs as $ua) {
            if (stripos($userAgent, $ua) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function isDevelopment(): bool
    {
        return defined('WP_ENV') && WP_ENV === 'development';
    }

    /**
     * Recursively filter out empty values from an array.
     *! make arrays returned values more compact by removing empty entries 
     *
     * @param array $dataArray The input array to filter.
     * @param bool|null $disable Optional flag to disable filtering in development environment.
     * @return array The filtered array with empty values removed.
     */
    public static function filterEmptyValues(array $dataArray, ?bool $disable = false): array
    {
        // In development environment, return data as is for easier debugging
        if (self::isDevelopment() && $disable) {
            return $dataArray;
        }

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

    /**
     * Parse deadline string to timestamp, trying multiple common formats
     * @param string $deadline
     * @return int|false
     */
    public static function parseDeadlineTimestamp(string $deadline): int|false
    {
        // Common date formats to try
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'];

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $deadline);
            if ($dt !== false) {
                return $dt->getTimestamp() + 86399; // Add 23:59:59 to the day
            }
        }

        // Fallback to strtotime
        $ts = strtotime($deadline . ' 23:59:59');
        return $ts !== false ? $ts : false;
    }
}