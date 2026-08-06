<?php
namespace WPLokerBJM\Core\Container\Support\WPHooks\Utilities;

class HookTagUtilities
{
    /**
     * Normalize a tag (or list of tags) to a unique list of strings.
     *
     * Accepted tag types: string, or a string-backed enum (normalized to its
     * `->value`, identical to the literal string). Anything else raises a
     * RuntimeException.
     *
     * @param string|array<int, mixed> $tags
     *
     * @return array<int, string>
     */
    public static function normalizeTags(string|array $tags): array
    {
        $normalized = array_map(static fn($tag) => self::normalizeTagValue($tag), (array) $tags);

        return array_values(array_unique($normalized));
    }

    /**
     * Normalize a single tag value to its string representation.
     *
     * @throws \RuntimeException when the value is neither a string nor a string-backed enum
     */
    public static function normalizeTagValue(mixed $tag): string
    {
        if (is_string($tag)) {
            return $tag;
        }

        if ($tag instanceof \BackedEnum && is_string($tag->value)) {
            return $tag->value;
        }

        throw new \RuntimeException(
            'Tag must be a string or backed enum, got ' . get_debug_type($tag)
        );
    }
}