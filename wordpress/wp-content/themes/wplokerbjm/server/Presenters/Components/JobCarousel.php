<?php

namespace WPLokerBJM\Presenters\Components;

use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Models\Schema\CustomFields;
class JobCarousel
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {
    }

    public function getProps(): array
    {
        $cacheKey = CacheKey::CAROUSEL_JOBS;
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $args = JobQuery::getCarouselArgs(-1);
        $query = new \WP_Query($args);

        $result = $this->jobRepository->queryJob($args);
        $jobs = $result['jobs'] ?? [];

        $sort = function ($a, $b) {
            $statusA = (int) ($a['status_pekerjaan'] ?? 0);
            $statusB = (int) ($b['status_pekerjaan'] ?? 0);
            // Prioritize: Pinned (3) > Urgent (2) > Normal (0)
            if ($statusA !== $statusB) {
                return $statusB <=> $statusA; // DESC
            }
            // Within same status, sort by deadline ASC
            $deadlineA = strtotime($a['deadline'] ?? '9999-12-31');
            $deadlineB = strtotime($b['deadline'] ?? '9999-12-31');
            return $deadlineA <=> $deadlineB; // ASC
        };

        // Sort jobs: status DESC (3 first), then deadline ASC
        usort($jobs, $sort);

        $props = [
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ];

        Cache::set($cacheKey, $props, 86400); // Cache for 1 day

        return $props;
    }
}