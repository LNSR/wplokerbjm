<?php
namespace WPLokerBJM\Shared\Utilities;
enum PluginList: string
{
    case LiteSpeed = 'litespeed-cache/litespeed-cache.php';
    case Wordfence = 'wordfence/wordfence.php';
    case MetaBox = 'meta-box/meta-box.php';
    case MetaBoxLite = 'meta-box-lite/meta-box-lite.php';
    case WpGraphql = 'wp-graphql/wp-graphql.php';
    case RankMath = 'seo-by-rank-math/rank-math.php';
    case QueryMonitor = 'query-monitor/query-monitor.php';
    case JwtAuthenticationForWpRestApi = 'jwt-authentication-for-wp-rest-api/jwt-auth.php';
    public function isActive(): bool {
        static $activePlugins = null;
        $activePlugins ??= get_option('active_plugins') ?: [];
        return is_array($activePlugins) && in_array($this->value, $activePlugins, true);
    }

    public function deactivePlugin(): void {
        if($this->isActive()) {
            deactivate_plugins($this->value, false);
        }
    }

    public function activePlugin(): void {
        if(!$this->isActive()) {
            activate_plugins($this->value, false);
        }
    }
}

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

    public static function doActivityAtBackground(callable $activity): void {
        if (defined('PHP_SAPI') && PHP_SAPI !== 'cli') {
            if (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            } elseif (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }
        $activity();
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