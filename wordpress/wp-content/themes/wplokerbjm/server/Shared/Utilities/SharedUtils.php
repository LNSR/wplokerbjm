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
     * Check if a plugin is active by inspecting the 'active_plugins' option in wp_options.
     * @param string $pluginKey one of: 'litespeed','wpgraphql','rankmath'
     * @return bool
     */
    public static function isPluginActive(string $pluginKey): bool
    {
        // Map pluginKey to plugin file slug
        $pluginMap = [
            'litespeed' => 'litespeed-cache/litespeed-cache.php',
            'litespeed-cache' => 'litespeed-cache/litespeed-cache.php',
            'wpgraphql' => 'wp-graphql/wp-graphql.php',
            'wp-graphql' => 'wp-graphql/wp-graphql.php',
            'rankmath' => 'seo-by-rank-math/rank-math.php',
            'rank-math' => 'seo-by-rank-math/rank-math.php',
        ];
        $pluginFile = $pluginMap[strtolower($pluginKey)] ?? null;
        if (!$pluginFile) {
            return false;
        }

        $checkPlugins = static function () {
            $plugins = get_option('active_plugins');
            return is_array($plugins) ? $plugins : null;
        };


        // Query the database for active_plugins
        $activePlugins = $checkPlugins();
        if (!is_array($activePlugins)) {
            return false;
        }
        return in_array($pluginFile, $activePlugins, true);
    }

    /**
     * Recursively filter out empty values from an array.
     *! make arrays returned values more compact by removing empty entries 
     *
     * @param array $dataArray The input array to filter.
     * @return array The filtered array with empty values removed.
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