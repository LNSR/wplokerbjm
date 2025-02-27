<?php

/**
 * Checks if a value is truly empty, including array validation
 * 
 * @param mixed $value The value to check
 * @return boolean True if empty, false otherwise
 */
function is_really_empty($value)
{
    if (is_array($value)) {
        return empty(array_filter($value));
    }
    return empty($value) && !is_numeric($value);
}

function format_age_range($min_age, $max_age)
{
    if (!is_really_empty($min_age) && !is_really_empty($max_age)) {
        return "{$min_age} - {$max_age} tahun";
    }
    return !is_really_empty($min_age) ? "Min {$min_age} tahun" : "Max {$max_age} tahun";
}

function format_salary_range($min_salary, $max_salary)
{
    if ($min_salary && $max_salary) {
        return 'IDR ' . number_format($min_salary, 0, ',', '.') . ' - ' . number_format($max_salary, 0, ',', '.');
    }
    return $min_salary ?
        'Minimal IDR ' . number_format($min_salary, 0, ',', '.') :
        'Maksimal IDR ' . number_format($max_salary, 0, ',', '.');
}

function format_education($education)
{
    return is_array($education) ? implode(', ', $education) : $education;
}

function format_experience($years)
{
    return $years . ' tahun';
}

function format_deadline($date)
{
    return date_i18n('d F Y', strtotime($date));
}

function has_job_summary($job_data)
{
    return !is_really_empty($job_data['job_type']) ||
        !is_really_empty($job_data['education']) ||
        !is_really_empty($job_data['experience']) ||
        !is_really_empty($job_data['gender']) ||
        !is_really_empty($job_data['min_age']) ||
        !is_really_empty($job_data['max_age']) ||
        !is_really_empty($job_data['min_salary']) ||
        !is_really_empty($job_data['max_salary']) ||
        !is_really_empty($job_data['location']) ||
        !is_really_empty($job_data['deadline']);
}

function has_contact_info($job_data)
{
    return !is_really_empty($job_data['email']) ||
        !is_really_empty($job_data['phone']) ||
        !is_really_empty($job_data['website']);
}

function translate_time_diff($time_diff)
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

function get_job_status_attributes($status)
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
?>