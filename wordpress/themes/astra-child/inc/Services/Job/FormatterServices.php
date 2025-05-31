<?php

namespace AstraChild\Services\Job;

class FormatterServices
{

    /**
     * Format salary range for IDR with business logic.
     *
     * @param int|null $gaji_minimal
     * @param int|null $gaji_maksimal
     * @return string|null
     */
    public static function formatSalary(?int $gaji_minimal, ?int $gaji_maksimal): ?string
    {
        $has_gaji_min = !empty($gaji_minimal);
        $has_gaji_max = !empty($gaji_maksimal);

        if (! $has_gaji_min && ! $has_gaji_max) {
            return null;
        }

        $formatter = new \NumberFormatter('id_ID', \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);

        $gaji_min = $has_gaji_min ? $formatter->formatCurrency($gaji_minimal, 'IDR') : null;
        $gaji_max = $has_gaji_max ? $formatter->formatCurrency($gaji_maksimal, 'IDR') : null;

        if ($has_gaji_min && $has_gaji_max) {
            return $gaji_min . ' - ' . $gaji_max;
        } elseif ($has_gaji_min) {
            return 'Sekitar ' . $gaji_min;
        } else {
            return 'Maksimal ' . $gaji_max;
        }
    }

    public static function formatAge(?int $umur_min, ?int $umur_max): ?string
    {
        $has_umur_min = !empty($umur_min);
        $has_umur_max = !empty($umur_max);

        if (! $has_umur_min && ! $has_umur_max) {
            return null;
        }

        if ($has_umur_min && $has_umur_max) {
            return $umur_min . ' - ' . $umur_max . ' tahun';
        } elseif ($has_umur_min) {
            return 'Minimal ' . $umur_min . ' tahun';
        } else {
            return 'Maksimal ' . $umur_max . ' tahun';
        }
    }

    /**
     * Format a phone number with spaces every 4 digits for better human readability.
     *
     * @param string $number The phone number to format.
     * @return string The formatted phone number.
     */
    public static function formatPhoneNumber($number)
    {
        // Remove all non-digit and non-plus characters
        $number = preg_replace('/[^\d+]/', '', $number);

        // If starts with +, extract country code (up to 5 digits after +)
        if (preg_match('/^\+(\d{1,5})(\d{0,})$/', $number, $matches)) {
            $countryCode = '+' . $matches[1];
            $rest = $matches[2];
            // Group the rest every 4 digits
            $formattedRest = trim(chunk_split($rest, 4, ' '));
            return trim($countryCode . ' ' . $formattedRest);
        } else {
            // No country code, just group every 4 digits
            $number = preg_replace('/\D+/', '', $number);
            return trim(chunk_split($number, 4, ' '));
        }
    }
}
