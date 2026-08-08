<?php

declare(strict_types=1);
use WPLokerBJM\Bootstrap;
// Composer autoloader — needed for vendor deps (RobotLoader, PHP-DI, etc.)
require_once __DIR__ . '/../vendor/autoload.php';

// Load Bootstrap class manually — we can't let RobotLoader auto-load it
// because the file also calls Bootstrap::boot() (WordPress functions).
// Define ABSPATH to satisfy the file's guard, and WPLOKERBJM_SKIP_BOOT
// (set in mu-plugins/wplokerbjm-bootstrap.php) to prevent boot().
define('ABSPATH', true);
define('WPLOKERBJM_SKIP_BOOT', true);
require_once __DIR__ . '/../../../mu-plugins/wplokerbjm-bootstrap.php';

// Nette RobotLoader for all WPLokerBJM classes — replaces Composer classmaps.
// Scans tests/, server/, and the mu-plugins Bootstrap file.
// Always refreshes in test environment; uses /tmp to avoid permission issues.
$testRl = (new \Nette\Loaders\RobotLoader)
    ->addDirectory(__DIR__)
    ->addDirectory(__DIR__ . '/../server')
    ->setTempDirectory(__DIR__ . '/cache/wplokerbjm-tests')
    ->setAutoRefresh(true)
    ->reportParseErrors(true);
$testRl->register();

// Share the test RobotLoader with Bootstrap so AutowireScanner and
// WPHooksScanner have access via Bootstrap::getRobotLoader().

Bootstrap::setRobotLoader($testRl);

require_once __DIR__ . '/Support/ProxyContainer.php';
require_once __DIR__ . '/Support/WplokerbjmTestCase.php';

// Initialize Brain Monkey for function mocking
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';
require_once __DIR__ . '/../vendor/brain/monkey/inc/api.php';

// Mock essential WordPress functions
// Note: These are now mocked in WplokerbjmTestCase::setUp() to ensure they run after Brain\Monkey\setup()

\WPLokerBJM\Tests\Support\ProxyContainer::boot();
