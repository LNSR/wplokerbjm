<?php

declare(strict_types=1);

use Psl\File;
use Psl\Iter;
use Psl\Str;
use Psl\Vec;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};

/**
 * Phase 4 — Collect #[Action] / #[Filter] hook registrations.
 *
 * Primary source: the WPHooksScanner cache file ({themeRoot}/cache/WPHooksCache.php).
 * It already contains class, method, hook, type and tags for every registered
 * hook — no parsing needed, and the results always match what the hook
 * registry actually uses at runtime.
 *
 * Falls back to a php-parser scan of theme files (last resort) when the cache
 * has not been generated yet, e.g. WordPress has never booted.
 *
 * @return array{actions: list<string>, filters: list<string>, tags: list<string>}
 */
function themeAttributeScan(string $themeRoot): array
{
    $fromCache = loadHookRegistrationsFromCache($themeRoot);

    if ($fromCache !== null) {
        info('Phase 4 — Reused WPHooks cache: ' . count($fromCache['actions']) . ' actions, '
            . count($fromCache['filters']) . ' filters, ' . count($fromCache['tags']) . ' tags');
        return $fromCache;
    }

    warning('WPHooks cache not found — falling back to php-parser scan');
    return themeAttributeScanWithParser($themeRoot);
}

/**
 * Load hook registrations from the WPHooksScanner cache file.
 *
 * @return array{actions: list<string>, filters: list<string>, tags: list<string>}|null
 */
function loadHookRegistrationsFromCache(string $themeRoot): ?array
{
    $cacheFile = $themeRoot . '/cache/WPHooksCache.php';

    if (!is_file($cacheFile)) {
        return null;
    }

    $registrations = require $cacheFile;

    if (!is_array($registrations)) {
        warning("WPHooks cache returned no registrations: {$cacheFile}");
        return null;
    }

    $actions = [];
    $filters = [];
    $tags = [];

    foreach ($registrations as $reg) {
        if (!is_array($reg)) {
            continue;
        }
        $hook = $reg['hook'] ?? null;
        if (!is_string($hook)) {
            continue;
        }
        if (($reg['type'] ?? null) === 'action') {
            $actions[] = $hook;
        } elseif (($reg['type'] ?? null) === 'filter') {
            $filters[] = $hook;
        }
        foreach ((array) ($reg['tags'] ?? []) as $tag) {
            if (is_string($tag) && $tag !== '') {
                $tags[] = $tag;
            }
        }
    }

    return [
        'actions' => Vec\unique($actions),
        'filters' => Vec\unique($filters),
        'tags' => Vec\unique($tags),
    ];
}

/**
 * Last-resort php-parser scan of theme files for #[Action] / #[Filter].
 *
 * Handles multiline attributes, nested parens, named arguments. Detects hook
 * attributes on both methods and public property closures. Also collects the
 * tag attribute (a string or an array of strings) so tag autocomplete keeps
 * working when the cache is unavailable.
 *
 * @return array{actions: list<string>, filters: list<string>, tags: list<string>}
 */
function themeAttributeScanWithParser(string $themeRoot): array
{
    $parser = (new ParserFactory())->createForNewestSupportedVersion();

    $collector = new class extends NodeVisitorAbstract {
        /** @var list<string> */
        public array $actions = [];
        /** @var list<string> */
        public array $filters = [];
        /** @var list<string> */
        public array $tags = [];

        public function enterNode(Node $node): ?int
        {
            if (!$node instanceof Node\AttributeGroup) {
                return null;
            }
            foreach ($node->attrs as $attr) {
                $this->processAttribute($attr);
            }
            return null;
        }

        private function processAttribute(Attribute $attr): void
        {
            $baseName = $attr->name->getLast();
            if ($baseName !== 'Action' && $baseName !== 'Filter') {
                return;
            }

            foreach ($attr->args as $arg) {
                if (!$arg instanceof Node\Arg) {
                    continue;
                }
                $argName = $arg->name?->name;

                if ($argName === null || $argName === 'hook') {
                    if (!$arg->value instanceof String_) {
                        continue;
                    }
                    if ($baseName === 'Action') {
                        $this->actions[] = $arg->value->value;
                    } else {
                        $this->filters[] = $arg->value->value;
                    }
                } elseif ($argName === 'tag') {
                    $this->collectTag($arg->value);
                }
            }
        }

        private function collectTag(Node $value): void
        {
            if ($value instanceof String_) {
                $this->tags[] = $value->value;
                return;
            }
            if ($value instanceof Array_) {
                foreach ($value->items as $item) {
                    if ($item !== null && $item->value instanceof String_) {
                        $this->tags[] = $item->value->value;
                    }
                }
            }
        }
    };

    $traverser = new NodeTraverser();
    $traverser->addVisitor($collector);

    $skipped = 0;
    foreach (findPhpFiles($themeRoot . '/server') as $file) {
        try {
            $content = File\read($file);
        } catch (\Throwable) {
            $skipped++;
            continue;
        }
        try {
            $ast = $parser->parse($content);
            if ($ast !== null) {
                $traverser->traverse($ast);
            }
        } catch (\Throwable) {
            $skipped++;
        }
    }

    if ($skipped > 0) {
        warning("Skipped {$skipped} unparseable theme files");
    }

    return [
        'actions' => Vec\unique($collector->actions),
        'filters' => Vec\unique($collector->filters),
        'tags' => Vec\unique($collector->tags),
    ];
}
