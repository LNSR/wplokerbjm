<?php

declare(strict_types=1);

use Psl\Async;
use Psl\Collection\MutableVector;
use Psl\DateTime\Duration;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Shell\Exception\FailedExecutionException;
use Psl\Str;
use Psl\Vec;

/**
 * Phase 1 — Scan directories/files with `grep` for do_action / apply_filters.
 *
 * @param list<string> $scanDirs
 * @param list<string> $scanFiles
 * @return array{actions: list<string>, filters: list<string>}
 */
function staticPhaseScan(array $scanDirs, array $scanFiles): array
{
    $grepper = static function (string $path, bool $isDir) use (&$actions, &$filters): void {
        $recurseFlag = $isDir ? 'r' : '';

        $patterns = [
            ['action', "do_action\( *'\K[^']+"],
            ['filter', "apply_filters\( *'\K[^']+"],
            ['action', "do_action_ref_array\( *'\K[^']+"],
            ['filter', "apply_filters_ref_array\( *'\K[^']+"],
            ['action', "do_action_deprecated\( *'\K[^']+"],
            ['filter', "apply_filters_deprecated\( *'\K[^']+"],
        ];

        foreach ($patterns as [$type, $pattern]) {
            try {
                $output = Shell\execute('grep', [
                    "-{$recurseFlag}soPh",
                    ...Vec\flat_map(
                        GREP_EXCLUDE_DIRS,
                        static fn(string $d): array => ['--exclude-dir=' . $d],
                    ),
                    $pattern,
                    $path,
                ], cancellation: new Async\TimeoutCancellationToken(
                    Duration::seconds(GREP_TIMEOUT),
                ));
            } catch (FailedExecutionException) {
                continue;
            }

            $lines = parseLines($output);
            if ($type === 'action') {
                $actions = Vec\concat($actions, $lines);
            } else {
                $filters = Vec\concat($filters, $lines);
            }
        }
    };

    $actions = [];
    $filters = [];

    foreach ($scanDirs as $dir) {
        if (Filesystem\is_directory($dir)) {
            $grepper($dir, true);
        }
    }
    foreach ($scanFiles as $file) {
        if (Filesystem\is_file($file)) {
            $grepper($file, false);
        }
    }

    return ['actions' => $actions, 'filters' => $filters];
}
