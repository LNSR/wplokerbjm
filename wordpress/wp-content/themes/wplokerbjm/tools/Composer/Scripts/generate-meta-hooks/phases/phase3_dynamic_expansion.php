<?php

declare(strict_types=1);

use Psl\Collection\MutableVector;
use Psl\Result;
use Psl\Str;
use Psl\Vec;
use function Psl\Async\concurrently;
use function Psl\Result\reflect;

/**
 * Phase 3 — Dynamic hook expansion via concurrent WP-CLI queries.
 *
 * Fetches option names, post types, taxonomies, roles, post statuses, and
 * registered block types from the live WordPress instance and expands common
 * dynamic hook patterns from them.
 *
 * Block types use `wp eval` — no native WP-CLI command exists.
 * Post statuses use `wp eval` + get_post_stati() — no native command.
 *
 * Uses Result::reflect for per-call error handling.
 *
 * @return array{actions: list<string>, filters: list<string>}
 */
function dynamicHookExpansion(?string $container, string $wpPath): array
{
    if ($container === null) {
        warning('Dynamic expansion: no container found — skipping');
        return ['actions' => [], 'filters' => []];
    }

    // ── Inline eval code for sources without native WP-CLI commands ──────
    $blockEvalCode  = 'echo implode("\n", array_keys(\WP_Block_Type_Registry::get_instance()->get_all_registered()));';
    $statusEvalCode = 'echo implode("\n", get_post_stati([], "names"));';

    // ── Run all 6 WP-CLI queries concurrently ───────────────────────────
    $raw = concurrently([
        'options'      => reflect(fn(): string => runWpCli($container, $wpPath, ['option', 'list', '--field=option_name'])),
        'postTypes'    => reflect(fn(): string => runWpCli($container, $wpPath, ['post-type', 'list', '--field=name'])),
        'taxonomies'   => reflect(fn(): string => runWpCli($container, $wpPath, ['taxonomy', 'list', '--field=name'])),
        'roles'        => reflect(fn(): string => runWpCli($container, $wpPath, ['role', 'list', '--field=name'])),
        'blocks'       => reflect(fn(): string => runWpCli($container, $wpPath, ['eval', $blockEvalCode])),
        'postStatuses' => reflect(fn(): string => runWpCli($container, $wpPath, ['eval', $statusEvalCode])),
    ]);

    $actions = new MutableVector([]);
    $filters = new MutableVector([]);

    /** @var list<string>|null */
    $postTypes = null;
    /** @var list<string>|null */
    $postStatuses = null;

    foreach ($raw as $key => $result) {
        if (!$result->isSucceeded()) {
            warning("Dynamic expansion — {$key} failed: {$result->getThrowable()->getMessage()}");
            continue;
        }

        $lines = Vec\filter(
            explode("\n", trim($result->getResult())),
            static fn(string $l): bool => $l !== ''
                && !Str\starts_with($l, 'Deprecated:')
                && !Str\starts_with($l, 'Warning:')
                && !Str\starts_with($l, 'Notice:')
                && !Str\starts_with($l, 'Fatal:'),
        );

        // ── Expand immediately where no deferred data is needed ──────────
        match ($key) {
            'options'    => expandOptionHooks($lines, $actions, $filters),
            'taxonomies' => expandTaxonomyHooks($lines, $actions, $filters),
            'roles'      => expandRoleHooks($lines, $actions),
            'blocks'     => expandBlockHooks($lines, $filters),
            default      => null,
        };

        // ── Store for deferred / combined expansions ────────────────────
        match ($key) {
            'postTypes'    => $postTypes = $lines,
            'postStatuses' => $postStatuses = $lines,
            default        => null,
        };
    }

    // ── Post-type expansion (independent, always runs) ──────────────────
    if ($postTypes !== null) {
        expandPostTypeHooks($postTypes, $actions, $filters);
    }

    // ── Status × type cartesian expansion ───────────────────────────────
    // wp_transition_post_status() fires do_action("{$new_status}_{$post_type}", ...)
    if ($postStatuses !== null && $postTypes !== null) {
        expandStatusTypeHooks($postStatuses, $postTypes, $actions);
    } elseif ($postStatuses !== null) {
        expandStatusTransitionHooks($postStatuses, $actions);
    }

    $result = [
        'actions' => $actions->toArray(),
        'filters' => $filters->toArray(),
    ];

    $total = count($result['actions']) + count($result['filters']);
    if ($total > 0) {
        info("  Phase 3 (dynamic): +{$total} hooks");
    }

    return $result;
}

// ── Expansion helpers ───────────────────────────────────────────────────────

/** @param list<string> $lines */
function expandOptionHooks(array $lines, MutableVector $actions, MutableVector $filters): void
{
    foreach ($lines as $opt) {
        $actions->addAll(Vec\map(
            ['update_option_', 'add_option_', 'added_option_', 'updated_option_', 'delete_option_', 'deleted_option_'],
            fn(string $prefix): string => $prefix . $opt,
        ));
        $filters->addAll(Vec\map(
            ['option_', 'pre_option_', 'default_option_', 'pre_update_option_'],
            fn(string $prefix): string => $prefix . $opt,
        ));
    }
}

/** @param list<string> $lines */
function expandPostTypeHooks(array $lines, MutableVector $actions, MutableVector $filters): void
{
    foreach ($lines as $pt) {
        $actions->addAll(Vec\map(
            ['manage_', 'save_post_', 'wp_after_insert_post_', 'delete_post_', 'rest_insert_'],
            fn(string $prefix): string => $prefix . $pt,
        ));
        $actions->add('manage_' . $pt . '_posts_custom_column');
        $filters->addAll(Vec\map(
            ['manage_', 'rest_', 'rest_prepare_'],
            fn(string $prefix): string => $prefix . $pt,
        ));
        $filters->add('manage_' . $pt . '_posts_columns');
        $filters->add('rest_' . $pt . '_query');
    }
}

/** @param list<string> $lines */
function expandTaxonomyHooks(array $lines, MutableVector $actions, MutableVector $filters): void
{
    foreach ($lines as $tax) {
        $actions->addAll(Vec\map(
            ['create_', 'edited_', 'delete_', 'deleted_'],
            fn(string $prefix): string => $prefix . $tax,
        ));
        $actions->addAll([
            $tax . '_add_form',
            $tax . '_add_form_fields',
            $tax . '_edit_form',
            $tax . '_edit_form_fields',
        ]);
        $filters->add($tax . '_row_actions');
    }
}

/** @param list<string> $lines */
function expandRoleHooks(array $lines, MutableVector $actions): void
{
    foreach ($lines as $role) {
        $actions->add('set_user_role_' . Str\lowercase($role));
    }
}

// ── New expansions ──────────────────────────────────────────────────────────

/**
 * Expand registered block types into render_block_{$name} filter hooks.
 *
 * apply_filters('render_block_' . $block_name, ...) fires for every block
 * during render. Each registered block type creates a unique filter.
 *
 * @param list<string> $blockNames
 */
function expandBlockHooks(array $blockNames, MutableVector $filters): void
{
    foreach ($blockNames as $blockName) {
        $filters->add('render_block_' . $blockName);
    }
}

/**
 * Expand post status × post-type cartesian product.
 *
 * wp_transition_post_status() fires:
 *   do_action("{$new_status}_{$post->post_type}", $post->ID, $post)
 *
 * Examples: publish_post, draft_page, future_lowongan
 *
 * @param list<string> $statuses
 * @param list<string> $postTypes
 */
function expandStatusTypeHooks(array $statuses, array $postTypes, MutableVector $actions): void
{
    foreach ($statuses as $status) {
        foreach ($postTypes as $pt) {
            $actions->add("{$status}_{$pt}");
        }
    }
}

/**
 * Fallback: status-to-status transition hooks (when post types unavailable).
 *
 * do_action("{$old_status}_to_{$new_status}", $post) fires in
 * wp_transition_post_status() for non-identical status transitions.
 *
 * @param list<string> $statuses
 */
function expandStatusTransitionHooks(array $statuses, MutableVector $actions): void
{
    foreach ($statuses as $old) {
        foreach ($statuses as $new) {
            if ($old !== $new) {
                $actions->add("{$old}_to_{$new}");
            }
        }
    }
}
