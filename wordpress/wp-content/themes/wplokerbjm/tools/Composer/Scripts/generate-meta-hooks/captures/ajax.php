<?php
/**
 * WP-CLI eval-file bootstrap: AJAX context.
 *
 * Sets DOING_AJAX=true before loading WordPress, then loads the shared admin
 * includes (wp-admin/includes/admin.php) to trigger AJAX-specific hook
 * registrations (wp_ajax_{action}, wp_ajax_nopriv_{action}, etc.).
 *
 * Usage:
 *   wp --skip-wordpress eval-file captures/ajax.php
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

// ── Set AJAX context before WordPress loads ─────────────────────────────────
if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', true);
}

$_SERVER['HTTP_HOST'] ??= 'localhost';
$_SERVER['REQUEST_URI'] ??= '/wp-admin/admin-ajax.php';
$_SERVER['REQUEST_METHOD'] ??= 'POST';

require_once $wpPath . '/wp-load.php';

// ── Bootstrap AJAX subsystem ────────────────────────────────────────────────
// Load the admin bootstrap (shared between admin and AJAX) so that
// AJAX-specific hooks from WordPress core and plugins are registered.
require_once ABSPATH . 'wp-admin/includes/admin.php';

// Trigger admin_init so that AJAX-registered actions become available
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
