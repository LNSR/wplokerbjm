#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate .phpstorm.meta.php — modular hook scanner entry point.
 *
 * Usage:
 *   php tools/Composer/Scripts/generate-meta-hooks.php
 *   (or via composer: composer run generate-meta-hooks)
 */

require __DIR__ . '/generate-meta-hooks/bootstrap.php';    // paths, autoload
require __DIR__ . '/generate-meta-hooks/config.php';       // constants, scan lists
require __DIR__ . '/generate-meta-hooks/helpers.php';      // all helper functions
require __DIR__ . '/generate-meta-hooks/phases/phase1_static_scan.php';
require __DIR__ . '/generate-meta-hooks/phases/phase2_runtime_capture.php';
require __DIR__ . '/generate-meta-hooks/phases/phase3_dynamic_expansion.php';
require __DIR__ . '/generate-meta-hooks/phases/phase4_theme_attributes.php';
require __DIR__ . '/generate-meta-hooks/main.php';         // main execution flow
