<?php

declare(strict_types=1);

use Psl\Env;
use Psl\Filesystem;
use Psl\Str;
use Psl\Vec;

// ── Configuration ──────────────────────────────────────────────────────────

const DOCKER_WP_PATH = '/var/www/html';
const MARKER_BEGIN = '// BEGIN-AUTO-GENERATED';
const MARKER_END = '// END-AUTO-GENERATED';
const SKIP_DIRS = ['/vendor/', '/node_modules/'];
const CONTAINER_NAMES = ['wordpress-development', 'wordpress-production', 'wordpress-staging'];
const GREP_EXCLUDE_DIRS = ['vendor', 'node_modules'];
const CHUNK_SIZE = 8;
const GREP_TIMEOUT = 120;
const DOCKER_TIMEOUT = 60;
const DOCKER_PS_TIMEOUT = 10;

// P6: Override container names and WP path from environment when available.
$containerNames = Str\split(Env\get_var('WP_CONTAINER_NAMES') ?: '', ',');
$containerNames = Vec\filter($containerNames, static fn(string $n): bool => $n !== '');
if ($containerNames === []) {
    $containerNames = CONTAINER_NAMES;
}
$dockerWpPath = Env\get_var('DOCKER_WP_PATH') ?: DOCKER_WP_PATH;

// ── Phase input preparation ────────────────────────────────────────────────

/**
 * @var list<string> $scanDirs
 */
$scanDirs = Vec\filter(
    [
        $wpRoot . '/wp-includes',
        $wpRoot . '/wp-admin',
        $wpRoot . '/wp-content/plugins',
        $wpRoot . '/wp-content/mu-plugins',
        $themeRoot . '/server',
    ],
    Filesystem\exists(...),
);

/**
 * @var list<string> $scanFiles
 */
$scanFiles = Vec\concat(
    Vec\filter(
        glob($wpRoot . '/*.php') ?: [],
        Filesystem\is_file(...),
    ),
    Vec\filter(
        glob($wpRoot . '/wp-content/*.php') ?: [],
        Filesystem\is_file(...),
    ),
);
