<?php

namespace AstraChild\Models;

use AstraChild\Helpers\JobHelpers;
/**
 * Job Entity
 * 
 * Represents a job post with its data
 */
class JobEntity extends EntityModel
{
    /**
     * @var JobModel
     */
    private $model;

    /**
     * Initialize the Job Entity
     *
     * @param array $attributes Initial attributes
     * @param JobModel|null $model Job model instance
     */
    public function __construct(array $attributes = [], JobModel $model = null)
    {
        if (!empty($attributes)) {
            $this->setAttributes($attributes);
        }
        $this->model = $model ?? new JobModel();
    }

    /**
     * Determine if the model exists in the storage
     *
     * @return bool
     */
    public function exists(): bool
    {
        return !empty($this->getAttribute('ID')) && get_post($this->getAttribute('ID')) !== null;
    }

    /**
     * Save the model
     *
     * @return bool
     */
    public function save(): bool
    {
        $result = $this->model->saveJob($this);

        if ($result && !$this->exists()) {
            $this->setAttribute('ID', $result);
        }

        return $result !== false;
    }

    /**
     * Get formatted salary range
     *
     * @return string
     */
    public function getFormattedSalary(): string
    {
        return $this->formatSalaryRange(
            $this->getAttribute('min_salary'),
            $this->getAttribute('max_salary')
        );
    }

    /**
     * Get formatted age range
     *
     * @return string
     */
    public function getFormattedAgeRange(): string
    {
        return $this->formatAgeRange(
            $this->getAttribute('min_age'),
            $this->getAttribute('max_age')
        );
    }

    /**
     * Get formatted education
     *
     * @return string
     */
    public function getFormattedEducation(): string
    {
        return $this->formatEducation($this->getAttribute('education'));
    }

    /**
     * Get formatted experience
     *
     * @return string
     */
    public function getFormattedExperience(): string
    {
        return $this->formatExperience($this->getAttribute('experience'));
    }

    /**
     * Get formatted deadline with Indonesian date
     * 
     * @return string
     */
    public function getFormattedDeadline(): string
    {
        if (!$this->hasAttribute('deadline')) {
            return '';
        }
        
        $deadline = $this->getAttribute('deadline');
        
        // Get formatted date
        $formatted_date = JobHelpers::formatIndonesianDate($deadline);
        
        return $formatted_date;
    }

    /**
     * Check if job has summary data
     *
     * @return bool
     */
    public function hasSummary(): bool
    {
        return $this->checkJobSummary($this->getAttributes());
    }

    /**
     * Check if job has contact information
     *
     * @return bool
     */
    public function hasContactInfo(): bool
    {
        return $this->checkContactInfo($this->getAttributes());
    }

    /**
     * Check if job is urgent
     *
     * @return bool
     */
    public function isUrgent(): bool
    {
        return in_array($this->getAttribute('status'), ['2', '4']);
    }

    /**
     * Check if job is pinned
     *
     * @return bool
     */
    public function isPinned(): bool
    {
        return in_array($this->getAttribute('status'), ['3', '4']);
    }

    /**
     * Check if the job deadline has passed
     */
    public function hasExpired(): bool
    {
        if (!$this->hasAttribute('deadline')) {
            return false;
        }

        $deadline = strtotime($this->getAttribute('deadline'));
        return $deadline < current_time('timestamp');
    }

    /**
     * Get days until deadline (negative if passed)
     */
    public function getDaysUntilDeadline(): int
    {
        if (!$this->hasAttribute('deadline')) {
            return 0;
        }

        $deadline = strtotime($this->getAttribute('deadline'));
        $now = current_time('timestamp');
        return ceil(($deadline - $now) / (60 * 60 * 24));
    }

    /**
     * Mark job as viewed
     */
    public function incrementViewCount(): void
    {
        if (!$this->exists()) {
            return;
        }

        $current_count = (int)$this->getAttribute('job_view_count', 0);
        update_post_meta($this->getAttribute('ID'), 'job_view_count', $current_count + 1);
        $this->setAttribute('job_view_count', $current_count + 1);
    }

    /**
     * Format salary range
     *
     * @param mixed $minSalary
     * @param mixed $maxSalary
     * @return string
     */
    private function formatSalaryRange($minSalary, $maxSalary): string
    {
        if (empty($minSalary) && empty($maxSalary)) {
            return 'Tidak ditentukan';
        }

        if (!empty($minSalary) && empty($maxSalary)) {
            return 'Minimal ' . number_format($minSalary, 0, ',', '.');
        }

        if (empty($minSalary) && !empty($maxSalary)) {
            return 'Maksimal ' . number_format($maxSalary, 0, ',', '.');
        }

        return number_format($minSalary, 0, ',', '.') . ' - ' . number_format($maxSalary, 0, ',', '.');
    }

    /**
     * Format age range
     *
     * @param mixed $minAge
     * @param mixed $maxAge
     * @return string
     */
    private function formatAgeRange($minAge, $maxAge): string
    {
        if (empty($minAge) && empty($maxAge)) {
            return 'Tidak ada batasan';
        }

        if (!empty($minAge) && empty($maxAge)) {
            return "Minimal $minAge tahun";
        }

        if (empty($minAge) && !empty($maxAge)) {
            return "Maksimal $maxAge tahun";
        }

        return "$minAge - $maxAge tahun";
    }

    /**
     * Format education level
     *
     * @param mixed $education
     * @return string
     */
    private function formatEducation($education): string
    {
        if (empty($education)) {
            return 'Tidak ditentukan';
        }

        if (is_array($education)) {
            return implode(', ', $education);
        }

        return (string)$education;
    }

    /**
     * Format experience requirement
     *
     * @param mixed $experience
     * @return string
     */
    private function formatExperience($experience): string
    {
        if (empty($experience)) {
            return 'Tidak diperlukan';
        }

        return $experience . ' ' . ($experience > 1 ? 'tahun' : 'tahun');
    }

    /**
     * Format deadline date
     *
     * @param string|null $deadline
     * @return string
     */
    private function formatDeadline($deadline): string
    {
        if (empty($deadline)) {
            return 'Tidak ditentukan';
        }

        $deadlineDate = strtotime($deadline);
        return date_i18n(get_option('date_format'), $deadlineDate);
    }

    /**
     * Check if job has summary data
     *
     * @param array $attributes
     * @return bool
     */
    private function checkJobSummary(array $attributes): bool
    {
        $summaryFields = ['education', 'job_type', 'gender', 'min_age', 'max_age', 'experience'];
        
        foreach ($summaryFields as $field) {
            if (!empty($attributes[$field])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if job has contact information
     *
     * @param array $attributes
     * @return bool
     */
    private function checkContactInfo(array $attributes): bool
    {
        $contactFields = ['email', 'phone', 'website', 'social_media'];
        
        foreach ($contactFields as $field) {
            if (!empty($attributes[$field])) {
                return true;
            }
        }
        
        return false;
    }
}
