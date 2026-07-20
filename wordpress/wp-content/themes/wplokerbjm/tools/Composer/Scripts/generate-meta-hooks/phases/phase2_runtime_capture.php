<?php

declare(strict_types=1);

use Psl\Async;
use Psl\DateTime\Duration;
use Psl\Shell;
use Psl\Shell\Exception\FailedExecutionException;
use Psl\Vec;

/**
 * Phase 2 — Runtime hook capture across multiple WordPress lifecycle contexts.
 *
 * Runs capture scripts inside the Docker container for each distinct request
 * context (frontend, admin, REST API, AJAX, cron) using `wp eval-file
 * --skip-wordpress` so that context constants can be set before WordPress
 * loads. Results are merged (union) across all contexts.
 *
 * Returns empty arrays if the container is unreachable or all contexts fail.
 *
 * @return array{actions: list<string>, filters: list<string>}
 */
function runtimeHookCapture(?string $container, string $wpPath): array
{
    if ($container === null) {
        warning('Runtime capture: no container found — skipping');
        return ['actions' => [], 'filters' => []];
    }

    $capturesDir = __DIR__ . '/../captures';

    // ── Context definitions ─────────────────────────────────────────────
    // Each context has a dedicated capture file that sets the appropriate
    // WP constants and/or bootstraps the sub-system.
    $contexts = [
        'front' => 'front.php',
        'admin' => 'admin.php',
        'rest'  => 'rest.php',
        'ajax'  => 'ajax.php',
        'cron'  => 'cron.php',
    ];

    $containerTmpDir = '/tmp/wphook-captures-' . time();
    $cancel = new Async\TimeoutCancellationToken(Duration::seconds(DOCKER_TIMEOUT));

    // ── Step 1: Copy capture files into the container ───────────────────
    try {
        Shell\execute('docker', [
            'exec', $container,
            'mkdir', '-p', $containerTmpDir,
        ], cancellation: $cancel);
    } catch (FailedExecutionException $e) {
        warning('Runtime capture: cannot create temp dir in container — ' . $e->getErrorOutput());
        return ['actions' => [], 'filters' => []];
    }

    /** @var array<string, string> Map of context name → container script path */
    $containerScripts = [];
    foreach ($contexts as $ctxName => $file) {
        $localPath = $capturesDir . '/' . $file;
        if (!is_file($localPath)) {
            warning("Runtime capture: capture file not found: {$localPath}");
            continue;
        }
        $containerPath = $containerTmpDir . '/' . $file;
        try {
            Shell\execute('docker', [
                'cp', $localPath, "{$container}:{$containerPath}",
            ], cancellation: $cancel);
            $containerScripts[$ctxName] = $containerPath;
        } catch (FailedExecutionException $e) {
            warning("Runtime capture: cannot cp {$file} to container — " . $e->getErrorOutput());
        }
    }

    if ($containerScripts === []) {
        warning('Runtime capture: no capture files could be copied to container — skipping');
        return ['actions' => [], 'filters' => []];
    }

    // ── Step 2: Run all contexts concurrently via fibers ────────────────
    $promises = [];
    foreach ($containerScripts as $ctx => $scriptPath) {
        $promises[$ctx] = static fn() => runSingleContext($container, $wpPath, $scriptPath);
    }

    $allResults = Async\concurrently($promises);

    // ── Step 3: Merge (union) across all contexts ───────────────────────
    $mergedActions = [];
    $mergedFilters = [];

    $succeededCount = 0;
    foreach ($allResults as $ctx => $result) {
        if ($result === null) {
            continue;
        }
        $succeededCount++;
        [$ctxActions, $ctxFilters] = $result;

        // Track per-context contributions for diagnostics
        $ctxActionCount = count($ctxActions);
        $ctxFilterCount = count($ctxFilters);
        $newActions = count(array_diff($ctxActions, $mergedActions));
        $newFilters = count(array_diff($ctxFilters, $mergedFilters));
        if ($newActions > 0 || $newFilters > 0) {
            info("    {$ctx}: {$ctxActionCount} actions (+{$newActions} new), {$ctxFilterCount} filters (+{$newFilters} new)");
        }

        $mergedActions = array_merge($mergedActions, $ctxActions);
        $mergedFilters = array_merge($mergedFilters, $ctxFilters);
    }

    $actions = array_values(array_unique($mergedActions));
    $filters = array_values(array_unique($mergedFilters));
    sort($actions);
    sort($filters);

    $total = count($actions);
    $fTotal = count($filters);
    if ($total > 0 || $fTotal > 0) {
        info("  Phase 2 (runtime): {$succeededCount}/" . count($containerScripts)
            . " contexts — {$total} actions, {$fTotal} filters");
    }

    // ── Step 4: Cleanup temp dir in container (best-effort) ─────────────
    try {
        Shell\execute('docker', [
            'exec', $container,
            'rm', '-rf', $containerTmpDir,
        ], cancellation: new Async\TimeoutCancellationToken(Duration::seconds(5)));
    } catch (FailedExecutionException) {
        // Non-critical — ignore cleanup failures
    }

    return ['actions' => $actions, 'filters' => $filters];
}

/**
 * Run a single context capture script inside the container.
 *
 * Copies, executes, and parses the output of one capture file via
 * `wp eval-file --skip-wordpress`. The WordPress path is resolved from
 * WP_CLI::get_runner()->config['path'] inside the captured script.
 *
 * @return array{0: list<string>, 1: list<string>}|null [actions, filters] or null on failure
 */
function runSingleContext(string $container, string $wpPath, string $scriptPath): ?array
{
    try {
        $output = runWpCli($container, $wpPath, [
            '--skip-wordpress',
            'eval-file',
            $scriptPath,
        ]);
    } catch (FailedExecutionException $e) {
        warning('Runtime capture context failed: ' . ($e->getErrorOutput() ?: $e->getMessage()));
        return null;
    }

    $actions = [];
    $filters = [];

    foreach (explode("\n", trim($output)) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'ACTIONS:')) {
            $actions = Vec\filter(
                explode(',', substr($line, 8)),
                fn(string $h): bool => $h !== '',
            );
        } elseif (str_starts_with($line, 'FILTERS:')) {
            $filters = Vec\filter(
                explode(',', substr($line, 8)),
                fn(string $h): bool => $h !== '',
            );
        }
    }

    return [$actions, $filters];
}
