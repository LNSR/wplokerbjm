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
     * @var JobModel Has methods like createJobEntity(), getJobMetaData(), etc.
     */
    private $model;

    /**
     * Initialize the Job Entity
     *
     * @param array $attributes Initial attributes
     */
    public function __construct(array $attributes = [])
    {
        if (!empty($attributes)) {
            $this->setAttributes($attributes);
        }
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
        $model = new JobModel();
        $result = $model->saveJob($this);

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
        return JobHelpers::formatSalaryRange(
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
        return JobHelpers::formatAgeRange(
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
        return JobHelpers::formatEducation($this->getAttribute('education'));
    }

    /**
     * Get formatted experience
     *
     * @return string
     */
    public function getFormattedExperience(): string
    {
        return JobHelpers::formatExperience($this->getAttribute('experience'));
    }

    /**
     * Get formatted deadline
     *
     * @return string
     */
    public function getFormattedDeadline(): string
    {
        return JobHelpers::formatDeadline($this->getAttribute('deadline'));
    }

    /**
     * Check if job has summary data
     *
     * @return bool
     */
    public function hasSummary(): bool
    {
        return JobHelpers::hasJobSummary($this->getAttributes());
    }

    /**
     * Check if job has contact information
     *
     * @return bool
     */
    public function hasContactInfo(): bool
    {
        return JobHelpers::hasContactInfo($this->getAttributes());
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
}
