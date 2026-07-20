<?php

declare(strict_types=1);

// ── Bootstrap — paths & autoload ───────────────────────────────────────────

// tools/Composer/Scripts/generate-meta-hooks → wplokerbjm theme root (up 4)
$themeRoot = dirname(__DIR__, 4);
// theme → themes → wp-content → wordpress root (up 3)
$wpRoot = dirname($themeRoot, 3);

require_once $themeRoot . '/vendor/autoload.php';
