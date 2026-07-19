<?php
/**
 * WP-CLI eval-file bootstrap: Frontend context.
 *
 * Loads WordPress in a standard frontend context without any special
 * request-type constants. Captures all registered + fired hooks on shutdown.
 *
 * Usage:
 *   wp --skip-wordpress eval-file captures/front.php
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
$_SERVER['REQUEST_URI'] ??= '/';

require_once $wpPath . '/wp-load.php';

add_action('shutdown', static function (): void {
    global $wp_filter, $wp_actions;

    $allHooks = array_keys($wp_filter ?? []);
    $actionNames = array_keys($wp_actions ?? []);

    // Actions: hooks that have been do_action'd
    $actions = array_values(array_unique($actionNames));
    sort($actions);
    WP_CLI::line('ACTIONS:' . implode(',', $actions));

    // Filters: registered hooks that were never do_action'd
    $filters = array_values(array_diff($allHooks, $actionNames));
    sort($filters);
    WP_CLI::line('FILTERS:' . implode(',', $filters));
});
