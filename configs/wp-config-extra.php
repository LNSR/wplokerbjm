<?php

define('ADVMO_CLOUDFLARE_R2_BUCKET', getenv('ADVMO_CLOUDFLARE_R2_BUCKET'));
define('ADVMO_CLOUDFLARE_R2_KEY', getenv('ADVMO_CLOUDFLARE_R2_KEY'));
define('ADVMO_CLOUDFLARE_R2_SECRET', getenv('ADVMO_CLOUDFLARE_R2_SECRET'));
define('ADVMO_CLOUDFLARE_R2_DOMAIN', getenv('ADVMO_CLOUDFLARE_R2_DOMAIN'));
define('ADVMO_CLOUDFLARE_R2_ENDPOINT', getenv('ADVMO_CLOUDFLARE_R2_ENDPOINT'));

define('WORDPRESS_API_TOKEN_DOMAIN', getenv('WORDPRESS_API_TOKEN_DOMAIN'));
define('CLOUDFLARE_ZONE_ID', getenv('CLOUDFLARE_ZONE_ID'));

define('WP_REDIS_SOCK', getenv('REDIS_SOCK'));
define('WP_REDIS_HOST', getenv('REDIS_HOST'));
define('WP_REDIS_PASSWORD', getenv('REDIS_PWD'));
define('WP_REDIS_DATABASE', getenv('REDIS_DB'));

if (!defined('WP_CACHE'))
  define('WP_CACHE', true);
define('WP_CACHE_KEY_SALT', getenv('WORDPRESS_WP_SITEURL'));

if (!defined('WP_ENV')) {
  define('WP_ENV', getenv('WP_ENV') ?: 'production');
}

define('JWT_AUTH_SECRET_KEY', getenv('JWT_AUTH_SECRET_KEY'));
define('JWT_AUTH_CORS_ENABLE', true);

define('WPLBJM_API_BASE_URL_DEV', getenv('WPLBJM_API_BASE_URL_DEV'));
define('WPLBJM_JWT_DEV', getenv('WPLBJM_JWT_DEV'));

// Auto-discover protocol and hostname from forwarded headers (for all proxies)
$hostname = 'localhost'; // default fallback
$protocol = 'http://'; // default fallback

// Prioritize forwarded headers over direct headers
if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
  $hostname = $_SERVER['HTTP_X_FORWARDED_HOST'];
} elseif (isset($_SERVER['HTTP_HOST'])) {
  $hostname = $_SERVER['HTTP_HOST'];
}

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
  $protocol = (strpos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) ? 'https://' : 'http://';
  if ($protocol === 'https://') {
    $_SERVER['HTTPS'] = 'on';
  }
} else {
  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
}

// Detect staging/dev subdomain for noindex
if (strpos($hostname, 'staging.') === 0 || strpos($hostname, 'dev.') === 0) {
  define('WP_LOKERBJM_NO_INDEX', true);
}

define('WP_HOME', $protocol . $hostname);
define('WP_SITEURL', $protocol . $hostname);

$host_no_port = preg_replace('/:\d+$/', '', $hostname);
if (preg_match('/^[^.]+\.(.+)$/', $host_no_port, $m)) {
  $cookie_domain = '.' . $m[1];
} else {
  $cookie_domain = $host_no_port;
}
define('COOKIE_DOMAIN', $cookie_domain);
define('ADMIN_COOKIE_PATH', '/');
define('COOKIEPATH', '/');
define('SITECOOKIEPATH', '/');

// If JWT issuer wasn't provided earlier, default it to the cookie domain.
if (!defined('JWT_AUTH_ISS')) {
  define('JWT_AUTH_ISS', $cookie_domain);
}


//! LiteSpeed Cache Configuration Flag
// https://docs.litespeedtech.com/lscache/lscwp/constants/
define('LITESPEED_CONF', true);

// LiteSpeed Cache Object Cache Configuration
define('LITESPEED_CONF__OBJECT__HOST', getenv('REDIS_SOCK'));
define('LITESPEED_CONF__OBJECT__DB_ID', getenv('REDIS_DB'));
define('LITESPEED_CONF__OBJECT__USER', '');
define('LITESPEED_CONF__OBJECT__PSWD', getenv('REDIS_PWD'));

if (defined('WP_ENV') && WP_ENV === 'development') {
  define('LITESPEED_DISABLE_ALL', false);
  define('LITESPEED_DEV', false);
}

switch (WP_ENV) {
  case 'development':
    // Debugging
    if (!defined('WP_DEBUG'))
      define('WP_DEBUG', true);
    if (!defined('WP_DEBUG_LOG'))
      define('WP_DEBUG_LOG', '/var/www/html/wp-content/debug/debug.log');
    if (!defined('WP_DEBUG_DISPLAY'))
      define('WP_DEBUG_DISPLAY', true);
    if (!defined('SCRIPT_DEBUG'))
      define('SCRIPT_DEBUG', true);
    if (!defined('WP_CACHE'))
      define('WP_CACHE', true);

    // Performance for dev
    define('WP_POST_REVISIONS', 3);
    define('AUTOSAVE_INTERVAL', 120);
    define('EMPTY_TRASH_DAYS', 7);

    // Cron/Updates
    define('DISABLE_WP_CRON', true);
    define('WP_AUTO_UPDATE_CORE', true);

    // Memory
    define('WP_MEMORY_LIMIT', '4096M');
    define('WP_MAX_MEMORY_LIMIT', '8196M');

    // Dev mode
    define('DISALLOW_FILE_EDIT', false);
    define('DISALLOW_FILE_MODS', false);
    define('WP_ENVIRONMENT_TYPE', 'development');
    define('WP_DEVELOPMENT_MODE', 'all');

    // Enable Query Monitor caps panel for debugging
    define('QM_ENABLE_CAPS_PANEL', true);

    // Asset concatenation/compression
    define('COMPRESS_CSS', false);
    define('COMPRESS_SCRIPTS', false);
    define('CONCATENATE_SCRIPTS', false);
    define('ENFORCE_GZIP', false);
    define('FS_METHOD', 'direct');
    break;
  case 'production':
  default:
    // Production settings
    if (!defined('WP_DEBUG'))
      define('WP_DEBUG', false);
    if (!defined('WP_DEBUG_DISPLAY'))
      define('WP_DEBUG_DISPLAY', false);
    if (!defined('SCRIPT_DEBUG'))
      define('SCRIPT_DEBUG', true);
    if (!defined('WP_DEBUG_LOG'))
      define('WP_DEBUG_LOG', '/var/www/html/wp-content/debug/debug.log');
    if (!defined('WP_CACHE')) {
      define('WP_CACHE', true);
    }
    define('DISALLOW_FILE_EDIT', false);
    define('DISALLOW_FILE_MODS', false);
    define('WP_ENVIRONMENT_TYPE', 'production');
    define('FORCE_SSL_ADMIN', true); // Force SSL for admin
    define('AUTOSAVE_INTERVAL', 300); // Autosave every 5 minutes
    define('EMPTY_TRASH_DAYS', 7); // Empty trash every week
    define('WP_POST_REVISIONS', 10); // Limit post revisions
    define('WP_MEMORY_LIMIT', '4096M'); // Set memory limit
    define('WP_MAX_MEMORY_LIMIT', '8196M'); // Set max memory limit
    define('WP_CRON_LOCK_TIMEOUT', 60); // Cron lock timeout

    define('WP_AUTO_UPDATE_CORE', true);
    define('DISABLE_WP_CRON', true);

    // Asset concatenation/compression
    define('CONCATENATE_SCRIPTS', false); // Combine JS files
    define('COMPRESS_SCRIPTS', true); // Compress JS
    define('COMPRESS_CSS', true); // Compress CSS

    // Security hardening
    define('DISALLOW_UNFILTERED_HTML', true);
    define('WP_HTTP_BLOCK_EXTERNAL', false);
    define('WP_ACCESSIBLE_HOSTS', 'api.wordpress.org'); // Allow updates

    define('FS_METHOD', 'direct');

    // Enable Query Monitor caps panel for debugging
    define('QM_ENABLE_CAPS_PANEL', true);
    break;
}

// Allow DB repair if enabled
define('WP_ALLOW_REPAIR', filter_var(getenv('WP_REPAIR'), FILTER_VALIDATE_BOOLEAN) ? true : false);
