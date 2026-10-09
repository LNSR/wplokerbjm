<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support;

/**
 * Raw terminal writer for test diagnostics.
 *
 * PHPUnit captures test output with ob_start() and then neutralizes control
 * characters: ANSI escape sequences are rewritten to their visible
 * "\u{NNNN}" form by PHPUnit\Util\Sanitizer, which strips the colours from
 * the diagnostic output produced by the test suite.
 *
 * Writing straight to STDOUT bypasses that output buffer, so ANSI colour
 * codes and emoji reach the terminal unchanged.
 */
final class Terminal
{
    /**
     * Write the given chunks to STDOUT without PHPUnit's output buffering.
     *
     * @param string ...$chunks Concatenated in order and written as-is.
     */
    public static function out(string ...$chunks): void
    {
        fwrite(STDOUT, implode('', $chunks));
    }
}
