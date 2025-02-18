<?php
/**
 * Checks if a value is truly empty, including array validation
 * 
 * @param mixed $value The value to check
 * @return boolean True if empty, false otherwise
 */
function is_really_empty($value) {
    if (is_array($value)) {
        return empty(array_filter($value));
    }
    return empty($value) && !is_numeric($value);
}

/**
 * Helper functions for job listing functionality
 */

function get_job_meta_data() {
    return [
        'company' => rwmb_meta('nama_perusahaan'),
        'company_desc' => rwmb_meta('tentang_perusahaan'),
        'job_desc' => rwmb_meta('deskripsi_pekerjaan'),
        'education' => rwmb_meta('pendidikan'),
        'job_type' => rwmb_meta('jenis_pekerjaan'),
        'gender' => rwmb_meta('gender'),
        'min_age' => rwmb_meta('umur_min'),
        'max_age' => rwmb_meta('umur_max'),
        'experience' => rwmb_meta('pengalaman'),
        'requirements' => rwmb_meta('persyaratan'),
        'min_salary' => rwmb_meta('gaji_minimal'),
        'max_salary' => rwmb_meta('gaji_maksimal'),
        'location' => rwmb_meta('lokasi'),
        'deadline' => rwmb_meta('deadline'),
        'email' => rwmb_meta('email_kontak'),
        'phone' => rwmb_meta('nomor_kontak'),
        'website' => rwmb_meta('situs_kontak'),
        'socials' => rwmb_meta('social_media')
    ];
}

function format_age_range($min_age, $max_age) {
    if (!is_really_empty($min_age) && !is_really_empty($max_age)) {
        return "{$min_age} - {$max_age} tahun";
    }
    return !is_really_empty($min_age) ? "Min {$min_age} tahun" : "Max {$max_age} tahun";
}

function format_salary_range($min_salary, $max_salary) {
    if ($min_salary && $max_salary) {
        return 'IDR ' . number_format($min_salary, 0, ',', '.') . ' - ' . number_format($max_salary, 0, ',', '.');
    }
    return $min_salary ? 
        'Minimal IDR ' . number_format($min_salary, 0, ',', '.') : 
        'Maksimal IDR ' . number_format($max_salary, 0, ',', '.');
}

function format_education($education) {
    return is_array($education) ? implode(', ', $education) : $education;
}

function format_experience($years) {
    return $years . ' tahun';
}

function format_deadline($date) {
    return date_i18n('d F Y', strtotime($date));
}

function has_job_summary($job_data) {
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

function has_contact_info($job_data) {
    return !is_really_empty($job_data['email']) ||
           !is_really_empty($job_data['phone']) ||
           !is_really_empty($job_data['website']);
}
?>