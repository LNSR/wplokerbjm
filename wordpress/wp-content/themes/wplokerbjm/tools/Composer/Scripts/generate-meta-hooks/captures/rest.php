<?php
/**
 * WP-CLI eval-file bootstrap: REST API context.
 *
 * Loads WordPress normally (REST_REQUEST is NOT set before load, as doing so
 * causes fatal errors from plugins that check the constant during their
 * bootstrap), then triggers rest_api_init and REST-specific hooks so that
 * REST-registered hooks appear in $wp_filter.
 *
 * Usage:
 *   wp --skip-wordpress eval-file captures/rest.php
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

$_SERVER['HTTP_HOST'] ??= 'localhost';
$_SERVER['REQUEST_URI'] ??= '/wp-json/';
$_SERVER['REQUEST_METHOD'] ??= 'GET';

require_once $wpPath . '/wp-load.php';

// ── Bootstrap REST API subsystem ────────────────────────────────────────────
// Trigger rest_api_init so that routes and REST-specific hooks are registered.
// We do NOT set REST_REQUEST here because it causes fatal errors in plugins
// that check the constant during bootstrap.
if (function_exists('do_action')) {
    do_action('rest_api_init');
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
