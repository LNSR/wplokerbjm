<?php

declare(strict_types=1);
// Composer autoloader — needed for vendor deps (RobotLoader, PHP-DI, etc.)
require_once __DIR__ . '/../vendor/autoload.php';

// Load Bootstrap class manually — we can't let RobotLoader auto-load it
// because the file also calls Bootstrap::boot() (WordPress functions).
// Define ABSPATH to satisfy the file's guard, and WPLOKERBJM_TEST_ENV
// (set in mu-plugins/wplokerbjm-bootstrap.php) to prevent boot().
define('ABSPATH', true);
define('WPLOKERBJM_TEST_ENV', true);

// Nette RobotLoader for all WPLokerBJM classes — replaces Composer classmaps.
// Scans tests/, server/, and the mu-plugins Bootstrap file.
// Always refreshes in test environment; uses /tmp to avoid permission issues.
global $testRobotLoader;
$testRobotLoader = (new \Nette\Loaders\RobotLoader)
    ->addDirectory(__DIR__)
    ->addDirectory(__DIR__ . '/../server')
    ->setCacheDirectory(__DIR__ . '/robotloader-cache/wplokerbjm-tests')
    ->setAutoRefresh(true)
    ->reportParseErrors(true);
$testRobotLoader->register();

// Real WordPress hook engine (add_action / add_filter / do_action /
// apply_filters / remove_action / ... / WP_Hook). Loaded once per process;
// the test harness resets the hook globals before each test.
if (!function_exists('add_action')) {
    require_once dirname(__DIR__, 4) . '/wp-includes/plugin.php';
}