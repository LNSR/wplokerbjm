<?php
namespace AstraChild\Helpers;

/**
 * Job Helpers
 * 
 * Static helper methods for job-related functionality
 */
class JobHelpers
{
    /**
     * Checks if a value is truly empty, including array validation
     * 
     * @param mixed $value The value to check
     * @return boolean True if empty, false otherwise
     */
    public static function isReallyEmpty($value): bool
    {
        if (is_array($value)) {
            return empty(array_filter($value));
        }
        return empty($value) && !is_numeric($value);
    }
    
    /**
     * Translate time differences from English to Indonesian
     * 
     * @param string $time_diff Time difference string
     * @return string Translated time difference
     */
    public static function translateTimeDiff(string $time_diff): string
    {
        // Array of English to Indonesian time translations with word boundaries
        $translations = array(
            '/\b(year|years)\b/' => 'tahun',
            '/\b(month|months)\b/' => 'bulan',
            '/\b(week|weeks)\b/' => 'minggu',
            '/\b(day|days)\b/' => 'hari',
            '/\b(hour|hours)\b/' => 'jam',
            '/\b(minute|minutes)\b/' => 'menit',
            '/\b(second|seconds)\b/' => 'detik'
        );

        // Use preg_replace with word boundaries to prevent partial word matches
        return preg_replace(array_keys($translations), array_values($translations), $time_diff);
    }
    
    /**
     * Get job status attributes for display
     * 
     * @param string $status Status code
     * @return array Status attributes (label, class, icon)
     */
    public static function getJobStatusAttributes(string $status): array
    {
        $attributes = [
            '0' => [
                'label' => 'Normal',
                'class' => '',
                'icon' => 'fas fa-briefcase'
            ],
            '2' => [
                'label' => 'Urgent',
                'class' => 'bg-red-100 text-red-800',
                'icon' => 'fas fa-fire-alt'
            ],
            '3' => [
                'label' => 'Pinned',
                'class' => 'bg-yellow-100 text-yellow-800',
                'icon' => 'fas fa-thumbtack'
            ],
            '4' => [
                'label' => 'Pinned & Urgent',
                'class' => 'bg-orange-100 text-orange-800',
                'icon' => 'fas fa-exclamation-circle'
            ]
        ];

        return isset($attributes[$status]) ? $attributes[$status] : $attributes['0'];
    }
}