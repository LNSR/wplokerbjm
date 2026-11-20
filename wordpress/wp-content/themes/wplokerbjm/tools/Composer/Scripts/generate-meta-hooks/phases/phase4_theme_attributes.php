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
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\HookRegistration;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\InstanceHookMetadata;

/**
 * Phase 4 — Collect #[Action] / #[Filter] hook registrations.
 *
 * Primary source: the WPHooksScanner cache file ({themeRoot}/cache/WPhooksRegistryContainerCache.php).
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
    $cacheFileContainerRegistry = $themeRoot . '/cache/WPhooksRegistryContainerCache.php';
    $cacheFileRuntimeRegistry = $themeRoot . '/cache/WPHooksInstanceObjectCache.php';

    if (!is_file($cacheFileContainerRegistry)) {
        return null;
    }
    // The cache files export DTOs via __set_state(), so their classes must be
    // loaded before the cache files are required. This script runs outside
    // WordPress, where the theme autoloader is not registered.
    require_once $themeRoot . '/server/Shared/Utilities/DataObject/AbstractDataObject.php';
    require_once $themeRoot . '/server/Core/Container/Support/WPHooks/Provider/CallableHookParams.php';
    require_once $themeRoot . '/server/Core/Container/Support/WPHooks/Registrations/HookRegistration.php';
    require_once $themeRoot . '/server/Core/Container/Support/WPHooks/Registrations/HookKey.php';
    require_once $themeRoot . '/server/Core/Container/Support/WPHooks/Registrations/InstanceHookMetadata.php';

    $registrationContainerRegistry = require $cacheFileContainerRegistry;
    $registrationRuntimeRegistry = is_file($cacheFileRuntimeRegistry)
        ? require $cacheFileRuntimeRegistry
        : null;


    if (!is_array($registrationContainerRegistry)) {
        warning("WPHooks cache returned no registrations: {$cacheFileContainerRegistry}");
        return null;
    }

    $actions = [];
    $filters = [];
    $tags = [];

    foreach ($registrationContainerRegistry as $reg) {
        $hook = $reg instanceof HookRegistration ? $reg->hook : ($reg['hook'] ?? null);
        if (!is_string($hook)) {
            continue;
        }
        if (($reg instanceof HookRegistration ? $reg->type : ($reg['type'] ?? null)) === 'action') {
            $actions[] = $hook;
        } elseif (($reg instanceof HookRegistration ? $reg->type : ($reg['type'] ?? null)) === 'filter') {
            $filters[] = $hook;
        }
        $regTags = $reg instanceof HookRegistration ? $reg->tags : ($reg['tags'] ?? []);
        foreach ((array) $regTags as $tag) {
            if (is_string($tag) && $tag !== '') {
                $tags[] = $tag;
            }
        }
    }
    if (!is_array($registrationRuntimeRegistry)) {
        warning("WPHooks cache returned no registrations: {$cacheFileRuntimeRegistry}");
        return [
            'actions' => Vec\unique($actions),
            'filters' => Vec\unique($filters),
            'tags' => Vec\unique($tags),
        ];
    }

    collectRuntimeRegistryHooks($registrationRuntimeRegistry, $actions, $filters);

    return [
        'actions' => Vec\unique($actions),
        'filters' => Vec\unique($filters),
        'tags' => Vec\unique($tags),
    ];
}

/**
 * Runtime metadata is nested by parent class → property → metadata list.
 * Walk it recursively so both the nested cache shape and legacy flat lists
 * are accepted.
 *
 * @param array<mixed> $cache
 * @param list<string> $actions
 * @param list<string> $filters
 */
function collectRuntimeRegistryHooks(array $cache, array &$actions, array &$filters): void
{
    foreach ($cache as $node) {
        if ($node instanceof InstanceHookMetadata) {
            if ($node->type === 'action') {
                $actions[] = $node->hook;
            } elseif ($node->type === 'filter') {
                $filters[] = $node->hook;
            }
            continue;
        }

        if (!is_array($node)) {
            continue;
        }

        $hook = $node['hook'] ?? null;
        $type = $node['type'] ?? null;
        if (is_string($hook) && ($type === 'action' || $type === 'filter')) {
            $type === 'action' ? $actions[] = $hook : $filters[] = $hook;
            continue;
        }

        collectRuntimeRegistryHooks($node, $actions, $filters);
    }
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
