<?php

namespace WPLokerBJM\Shared\Utilities;

use WPLokerBJM\Models\Schema\CustomFields;

/**
 * Centralized sanitization and validation utilities.
 *
 * All field-type-specific sanitization for the WPLokerBJM domain
 * lives here, so there is a single source of truth rather than
 * duplicated logic across controllers, factories, and utilities.
 */
class Sanitizer
{
    /**
     * Split a string by delimiter, trim each part, and discard empties.
     *
     * This replaces the recurring explode → array_map('trim', ...) → array_filter
     * pattern seen across ControllerUtils, IngestController, and elsewhere.
     *
     * @param string $delimiter The boundary string (e.g. ',', ';')
     * @param string $value     The raw input string
     * @return list<non-empty-string> Cleaned, non-empty parts
     */
    public static function splitAndClean(string $delimiter, string $value): array
    {
        /** @var list<non-empty-string> $result */
        $result = array_values(array_filter(
            array_map('trim', explode($delimiter, $value)),
            static fn(string $part): bool => $part !== ''
        ));

        return $result;
    }

    /**
     * Sanitize a single contact value by its field key.
     *
     * @param string $field The contact field key (email_kontak, situs_kontak, etc.)
     * @param string $value Raw value
     * @return non-empty-string Sanitized value (empty string if invalid — caller should filter)
     */
    public static function contactField(string $field, string $value): string
    {
        return match ($field) {
            CustomFields::EMAIL_KONTAK => sanitize_email($value),
            CustomFields::SITUS_KONTAK => esc_url_raw($value),
            default                   => sanitize_text_field($value),
        };
    }

    /**
     * Sanitize a contact field that may contain one or more values
     * (comma-separated string or already an array from cloned Meta Box fields).
     *
     * @param string          $field The contact field key
     * @param string|string[] $value Raw value(s)
     * @return list<non-empty-string> Sanitized, non-empty values
     */
    public static function contactFieldList(string $field, array|string $value): array
    {
        /** @var list<string> $rawParts */
        $rawParts = is_array($value)
            ? array_map('strval', $value)
            : self::splitAndClean(',', $value);

        /** @var list<non-empty-string> $result */
        $result = array_values(array_filter(
            array_map(static fn(string $part): string => self::contactField($field, $part), $rawParts),
            static fn(string $part): bool => $part !== ''
        ));

        return $result;
    }

    /**
     * Validate and cast a value to a positive/negative integer.
     * Returns null for non-integer values (floats, mixed types, etc.).
     *
     * @param mixed $value Raw value
     * @return int|null Integer value, or null if not a valid integer
     */
    public static function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Sanitize WYSIWYG content for safe storage (kses only).
     *
     * @param string|null $content Raw HTML content
     * @return string|null Sanitized content, or null when input was empty/null
     */
    public static function wysiwyg(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        $sanitized = wp_kses_post($content);

        return $sanitized !== '' ? $sanitized : null;
    }

    /**
     * Prepare WYSIWYG content for display (wpautop + shortcodes + kses).
     * This is the full rendering pipeline used in templates / API responses.
     *
     * @param string|null $content Raw HTML content
     * @return string|null Processed content, or null when input was empty/null
     */
    public static function wysiwygDisplay(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        $processed = do_shortcode(wpautop(wp_kses_post($content)));

        return $processed !== '' ? $processed : null;
    }

    /**
     * Validate a deadline date string in strict YYYY-MM-DD format.
     *
     * @param string|null $value Raw date string
     * @return string|null Normalized Y-m-d date, or null when invalid/empty
     */
    public static function deadline(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) !== 1) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Parse a flexible date string into Y-m-d format using strtotime.
     *
     * @param string|null $value Raw date string (anything strtotime can parse)
     * @return string|null Normalized Y-m-d date, or null when unparseable/empty
     */
    public static function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime(trim($value));

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }
}
