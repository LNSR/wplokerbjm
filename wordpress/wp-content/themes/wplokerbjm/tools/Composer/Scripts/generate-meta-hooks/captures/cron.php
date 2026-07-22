<?php
/**
 * WP-CLI eval-file bootstrap: Cron context.
 *
 * Sets DOING_CRON=true before loading WordPress, then triggers cron-related
 * hook registrations. Cron-specific hooks include wp_cron, wp_scheduled_delete,
 * wp_privacy_delete_old_export_files, and the various wp_{schedule} events.
 *
 * Usage:
 *   wp --skip-wordpress eval-file captures/cron.php
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

// ── Set cron context before WordPress loads ─────────────────────────────────
if (!defined('DOING_CRON')) {
    define('DOING_CRON', true);
}

$_SERVER['HTTP_HOST'] ??= 'localhost';
$_SERVER['REQUEST_URI'] ??= '/wp-cron.php';

require_once $wpPath . '/wp-load.php';

// ── Bootstrap cron subsystem ────────────────────────────────────────────────
// In cron context, WordPress core does not fire admin_init. We trigger it
// here so that plugins can register cron-specific hooks. We also trigger
// wp_loaded which is the standard WordPress lifecycle point where cron
// events are dispatched.
if (!wp_installing()) {
    do_action('wp_loaded');
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
