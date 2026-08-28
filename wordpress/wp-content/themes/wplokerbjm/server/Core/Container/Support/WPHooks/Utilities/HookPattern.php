<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Utilities;

/**
 * Shared validation and matching for hook-name and tag wildcard patterns.
 *
 * Patterns follow a single convention: exactly one trailing asterisk with a
 * literal prefix of at least two characters (the shortest real WordPress hook
 * name is 'wp'). All pattern-based activate/unregister methods route through
 * this class so the rules cannot drift between the registry and the deferred
 * hook manager.
 *
 * @internal
 */
class HookPattern
{
    /**
     * @param string $pattern Wildcard pattern, e.g. 'mail_*'.
     *
     * @throws \InvalidArgumentException When the pattern is empty, does not
     *                                   contain exactly one trailing asterisk,
     *                                   or its literal prefix is shorter than
     *                                   two characters.
     */
    public static function assertValid(string $pattern): void
    {
        if ($pattern === '') {
            throw new \InvalidArgumentException('Hook pattern must not be empty.');
        }

        if (substr_count($pattern, '*') !== 1 || !str_ends_with($pattern, '*')) {
            throw new \InvalidArgumentException(
                sprintf('Hook pattern "%s" must contain exactly one trailing asterisk.', $pattern)
            );
        }

        if (strlen($pattern) < 3) {
            throw new \InvalidArgumentException(
                sprintf('Hook pattern "%s" must have a literal prefix of at least 2 characters.', $pattern)
            );
        }
    }

    /**
     * Whether a concrete value matches a pattern (exact match when the
     * pattern carries no asterisk; prefix match otherwise).
     */
    public static function matches(string $value, string $pattern): bool
    {
        $star = strrpos($pattern, '*');

        if ($star === false) {
            return $value === $pattern;
        }

        return str_starts_with($value, substr($pattern, 0, $star));
    }

    /**
     * Validate every pattern in a list.
     *
     * @param array<int, string> $patterns Wildcard patterns.
     *
     * @throws \InvalidArgumentException When any pattern is invalid.
     */
    public static function assertValidAll(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            self::assertValid($pattern);
        }
    }

    /**
     * Whether any value matches any of the patterns (union of families).
     *
     * @param array<int, string> $values Concrete values, e.g. resolved tags.
     * @param array<int, string> $patterns Validated wildcard patterns.
     */
    public static function matchesAny(array $values, array $patterns): bool
    {
        foreach ($values as $value) {
            foreach ($patterns as $pattern) {
                if (self::matches($value, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }
}
