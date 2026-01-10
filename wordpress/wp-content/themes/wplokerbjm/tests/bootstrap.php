<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/ProxyContainer.php';
require_once __DIR__ . '/Support/WplokerbjmTestCase.php';

// Initialize Brain Monkey for function mocking
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';
require_once __DIR__ . '/../vendor/brain/monkey/inc/api.php';

// Mock essential WordPress functions
// Note: These are now mocked in WplokerbjmTestCase::setUp() to ensure they run after Brain\Monkey\setup()

\WPLokerBJM\Tests\Support\ProxyContainer::boot();