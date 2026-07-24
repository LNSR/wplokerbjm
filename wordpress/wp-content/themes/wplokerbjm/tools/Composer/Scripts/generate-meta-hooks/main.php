<?php

declare(strict_types=1);

use Psl\File;
use Psl\File\WriteMode;
use Psl\Filesystem;
use Psl\Vec;
use Psl\Async;

// ── Entry — main execution flow ────────────────────────────────────────────

// ── Phases 1-3: Run all discovery phases concurrently via fibers ───────────
// manually via enums or class constants (see config.php).
$start = hrtime(true);
$container = findContainer($containerNames);

$allPhases = Async\main(static function() use ($scanDirs, $scanFiles, $container, $dockerWpPath, $themeRoot): int {
    $start = hrtime(true);

$allPhases = Async\concurrently([
    'phase1' => static fn(): array => staticPhaseScan($scanDirs, $scanFiles),
    'phase2' => static fn(): array => runtimeHookCapture($container, $dockerWpPath),
    'phase3' => static fn(): array => dynamicHookExpansion($container, $dockerWpPath),
    // 'phase4' => static fn(): array => themeAttributeScan($themeRoot),
]);

    $elapsed = number_format((hrtime(true) - $start) / 1e9, 2);

    // Per-phase individual reports
$labels = [
    'phase1' => 'Phase 1 — Static grep scan',
    'phase2' => 'Phase 2 — Runtime capture',
    'phase3' => 'Phase 3 — Dynamic expansion',
    // 'phase4' => 'Phase 4 — Theme attribute scan',
];
    foreach ($labels as $key => $label) {
        $p = $allPhases[$key];
        $a = count($p['actions']);
        $f = count($p['filters']);
        info("{$label}: {$a} actions, {$f} filters");
    }
    info("Phases 1-3 concurrent: {$elapsed}s");

    // ── Phase 4: Merge with authoritative conflict resolution ────────────
    // Phase 1 (static grep), Phase 3 (pattern expansion), and Phase 4 (attribute scan)
    // are authoritative because they classify by source-code context.
    // Phase 2 (runtime) is unreliable: it captures hooks from $wp_filter
    // but can't distinguish actions from filters for hooks that never fire
    // during the eval request (e.g., wp_head, wp_enqueue_scripts).
    $authActions = array_unique(array_merge(
        $allPhases['phase1']['actions'],
        $allPhases['phase3']['actions'],
        // $allPhases['phase4']['actions'],
    ));
    $authFilters = array_unique(array_merge(
        $allPhases['phase1']['filters'],
        $allPhases['phase3']['filters'],
        // $allPhases['phase4']['filters'],
    ));

    // Phase 2: only add hooks that authorities don't disagree about
    $p2Actions = array_diff(
        $allPhases['phase2']['actions'],
        $authFilters, // don't add as action if authorities say it's a filter
    );
    $p2Filters = array_diff(
        $allPhases['phase2']['filters'],
        $authActions, // don't add as filter if authorities say it's an action
    );

    // Also remove from authorities anything Phase 2 misclassifies the
    // other way — handles hooks authorities disagree on
    $actions = Vec\concat($authActions, $p2Actions);
    $filters = Vec\concat($authFilters, $p2Filters);

    if ($actions === [] && $filters === []) {
        error('No hooks found from any phase — aborting');
        return 1;
    }

    // ── Phase 5: Normalize ─────────────────────────────────────────────
    $mergeStart = hrtime(true);
    $actions = normalizeHooks($actions);
    $filters = normalizeHooks($filters);
    $mergeElapsed = number_format((hrtime(true) - $mergeStart) / 1e9, 2);
    info("Merge + normalize: {$mergeElapsed}s");

    $total = number_format((hrtime(true) - $start) / 1e9, 2);
    info("Final: " . count($actions) . " actions, " . count($filters) . " filters (total: {$total}s)");

    // ── Phase 6: Generate metadata ─────────────────────────────────────
    $metaFile = $themeRoot . '/.phpstorm.meta.php';

    if (!Filesystem\exists($metaFile)) {
        info("Creating boilerplate {$metaFile}");
        $boilerplate = createBoilerplate();
        File\write($metaFile, $boilerplate, WriteMode::MustCreate);
    }

    $metaContent = File\read($metaFile);
    $generated = renderMetadataSection($actions, $filters);
    $metaContent = replaceGeneratedSection($metaContent, $generated);

    File\write($metaFile, $metaContent, WriteMode::Truncate);
    info("Updated {$metaFile}");

    return 0;
});
