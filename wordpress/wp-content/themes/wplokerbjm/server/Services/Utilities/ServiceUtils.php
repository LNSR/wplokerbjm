<?php

declare(strict_types=1);

namespace WPLokerBJM\Services\Utilities;

use WPLokerBJM\Models\Schema\CustomFields;
use WPLokerBJM\Shared\Utilities\Sanitizer;

class ServiceUtils
{
    /**
     * @param mixed $value
     * @return bool
     */
    public static function hasNonEmptyValue($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::hasNonEmptyValue($item)) {
                    return true;
                }
            }
            return false;
        }

        return trim((string) $value) !== '';
    }
}

class ServiceIngestUtils
{
    /**
     * Sanitize social media fieldset data from Meta Box.
     *
     * @param string|array<int, array<string, string>>|array<string, string> $value Raw social media data
     * @return list<array<string, non-empty-string>> Sanitized social media sets
     */
    public static function sanitizeSocialMediaFieldset($value): array
    {
        $allowedIndex = CustomFields::SOCIAL_MEDIA_PLATFORMS;

        if (is_string($value)) {
            $value = self::parseSocialMediaString($value);
        }

        if (!is_array($value)) {
            return [];
        }

        $sets = self::isAssoc($value) ? [$value] : $value;
        $sanitizedSets = [];

        foreach ($sets as $set) {
            if (!is_array($set)) {
                continue;
            }

            $sanitizedSet = [];
            foreach ($set as $platform => $username) {
                $platform = sanitize_text_field((string) $platform);
                if (!isset($allowedIndex[$platform])) {
                    continue;
                }

                $username = sanitize_text_field((string) $username);
                if ($username === '') {
                    continue;
                }

                $sanitizedSet[$platform] = $username;
            }

            if ($sanitizedSet !== []) {
                $sanitizedSets[] = $sanitizedSet;
            }
        }

        return $sanitizedSets;
    }

    /**
     * Parse a social media string format "platform:username;platform:username" into an array set.
     *
     * @param string $value Semicolon-separated platform:username pairs
     * @return list<array<string, string>> Single-element list containing the parsed set, or empty list
     */
    private static function parseSocialMediaString(string $value): array
    {
        $set = [];

        $items = Sanitizer::splitAndClean(';', $value);

        foreach ($items as $item) {
            $parts = explode(':', $item, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $platform = trim($parts[0]);
            $username = trim($parts[1]);
            if ($platform !== '' && $username !== '') {
                $set[$platform] = $username;
            }
        }

        return $set === [] ? [] : [$set];
    }

    /**
     * @param array $value
     * @return bool
     */
    private static function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}