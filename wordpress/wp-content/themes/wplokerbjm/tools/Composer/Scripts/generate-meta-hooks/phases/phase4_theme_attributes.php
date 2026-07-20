<?php

declare(strict_types=1);

use Psl\File;
use Psl\Iter;
use Psl\Str;
use Psl\Vec;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * !Omitted, this might useful in future
 * Phase 4 — Scan theme PHP files for #[Action] / #[Filter] attributes using
 * php-parser (handles multiline attributes, nested parens, named arguments).
 *
 * @return array{actions: list<string>, filters: list<string>}
 */
function themeAttributeScan(string $themeRoot): array
{
    $parser = (new ParserFactory())->createForNewestSupportedVersion();

    $collector = new class extends NodeVisitorAbstract {
        /** @var list<string> */
        public array $actions = [];
        /** @var list<string> */
        public array $filters = [];

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
                if (
                    $arg instanceof Node\Arg
                    && $arg->name?->name === 'hook'
                    && $arg->value instanceof String_
                ) {
                    if ($baseName === 'Action') {
                        $this->actions[] = $arg->value->value;
                    } else {
                        $this->filters[] = $arg->value->value;
                    }
                    return;
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

    return ['actions' => $collector->actions, 'filters' => $collector->filters];
}
