<?php
/**
 * WP-CLI eval-file bootstrap: Admin context.
 *
 * Sets WP_ADMIN=true before loading WordPress, then includes the admin
 * bootstrap (wp-admin/includes/admin.php) after WordPress loads to trigger
 * admin-specific hook registrations and firings (admin_init, admin_menu,
 * load-{page}, current_screen, etc.).
 *
 * Usage:
 *   wp --skip-wordpress eval-file captures/admin.php
 *
 * The WordPress path is resolved from WP_CLI::get_runner()->config['path'].
 */

declare(strict_types=1);

$wpPath = defined('WP_CLI') && ($runner = \WP_CLI::get_runner()) && isset($runner->config['path'])
    ? $runner->config['path']
    : '';

if ($wpPath === '' || !is_file($wpPath . '/wp-load.php')) {
    fwrite(STDERR, "FATAL: Invalid or missing WordPress path: {$wpPath}\n");
    exit(1);
}

// ── Set admin context before WordPress loads ────────────────────────────────
if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}
if (!defined('WP_NETWORK_ADMIN')) {
    define('WP_NETWORK_ADMIN', false);
}
if (!defined('WP_USER_ADMIN')) {
    define('WP_USER_ADMIN', false);
}

$_SERVER['HTTP_HOST'] ??= 'localhost';
$_SERVER['REQUEST_URI'] ??= '/wp-admin/index.php';

require_once $wpPath . '/wp-load.php';

// ── Bootstrap admin subsystem ───────────────────────────────────────────────
// This triggers admin-specific hook registrations (admin_init, admin_menu,
// load-*, current_screen, etc.) and causes plugins to register hooks that
// are guarded by is_admin() checks.
require_once ABSPATH . 'wp-admin/includes/admin.php';

// Trigger admin_init to fire admin-specific hook registrations
if (!wp_installing()) {
    do_action('admin_init');
}

add_action('shutdown', static function (): void {
    global $wp_filter, $wp_actions;

    $allHooks = array_keys($wp_filter ?? []);
    $actionNames = array_keys($wp_actions ?? []);

    $actions = array_values(array_unique($actionNames));
    sort($actions);
    WP_CLI::line('ACTIONS:' . implode(',', $actions));

    $filters = array_values(array_diff($allHooks, $actionNames));
    sort($filters);
    WP_CLI::line('FILTERS:' . implode(',', $filters));
});
