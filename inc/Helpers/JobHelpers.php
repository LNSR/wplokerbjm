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
     * Format age range for display
     * 
     * @param mixed $min_age Minimum age
     * @param mixed $max_age Maximum age
     * @return string Formatted age range
     */
    public static function formatAgeRange($min_age, $max_age): string
    {
        if (!self::isReallyEmpty($min_age) && !self::isReallyEmpty($max_age)) {
            return "{$min_age} - {$max_age} tahun";
        }
        return !self::isReallyEmpty($min_age) ? "Min {$min_age} tahun" : "Max {$max_age} tahun";
    }
    
    /**
     * Format salary range for display
     * 
     * @param mixed $min_salary Minimum salary
     * @param mixed $max_salary Maximum salary
     * @return string Formatted salary range
     */
    public static function formatSalaryRange($min_salary, $max_salary): string
    {
        if ($min_salary && $max_salary) {
            return 'IDR ' . number_format($min_salary, 0, ',', '.') . ' - ' . number_format($max_salary, 0, ',', '.');
        }
        return $min_salary ?
            'Minimal IDR ' . number_format($min_salary, 0, ',', '.') :
            'Maksimal IDR ' . number_format($max_salary, 0, ',', '.');
    }
    
    /**
     * Format education for display
     * 
     * @param mixed $education Education data
     * @return string Formatted education
     */
    public static function formatEducation($education): string
    {
        return is_array($education) ? implode(', ', $education) : $education;
    }
    
    /**
     * Format experience for display
     * 
     * @param mixed $years Years of experience
     * @return string Formatted experience
     */
    public static function formatExperience($years): string
    {
        return $years . ' tahun';
    }
    
    /**
     * Format deadline for display
     * 
     * @param string $date Date string
     * @return string Formatted deadline date
     */
    public static function formatDeadline(string $date): string
    {
        return date_i18n('d F Y', strtotime($date));
    }
    
    /**
     * Check if job has summary data
     * 
     * @param array $job_data Job metadata
     * @return boolean True if has summary data, false otherwise
     */
    public static function hasJobSummary(array $job_data): bool
    {
        return !self::isReallyEmpty($job_data['job_type']) ||
            !self::isReallyEmpty($job_data['education']) ||
            !self::isReallyEmpty($job_data['experience']) ||
            !self::isReallyEmpty($job_data['gender']) ||
            !self::isReallyEmpty($job_data['min_age']) ||
            !self::isReallyEmpty($job_data['max_age']) ||
            !self::isReallyEmpty($job_data['min_salary']) ||
            !self::isReallyEmpty($job_data['max_salary']) ||
            !self::isReallyEmpty($job_data['location']) ||
            !self::isReallyEmpty($job_data['deadline']);
    }
    
    /**
     * Check if job data has contact information
     * 
     * @param array $job_data Job metadata
     * @return boolean True if has contact info, false otherwise
     */
    public static function hasContactInfo(array $job_data): bool
    {
        return !self::isReallyEmpty($job_data['email']) ||
            !self::isReallyEmpty($job_data['phone']) ||
            !self::isReallyEmpty($job_data['website']);
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