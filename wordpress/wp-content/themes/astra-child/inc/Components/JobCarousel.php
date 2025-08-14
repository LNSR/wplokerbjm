<?php

namespace AstraChild\Components;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Repositories\JobRepository;
use AstraChild\Services\REST\RESTData;

class JobCarousel
{
    public function __construct(
        private JobRepository $jobRepository,
        private RESTData $restData
    ) {
    }

    /**
     * Render the root element for Vue and embed server-rendered job data.
     */
    public function render(): string
    {
        $args = JobQuery::getCarouselArgs(-1);
        $query = new \WP_Query($args);

        $jobs = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $jobs[] = $this->restData->getCardData(get_the_ID());
            }
            wp_reset_postdata();
        }

        $props = [
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ];
        $jsonProps = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
        <section id="job-carousel" class="min-h-[450px] md:min-h-[400px] lg:min-h-[500px]" data-props="<?php echo $jsonProps; ?>"></section>
        <?php
        return ob_get_clean();
    }

    public function getProps(): array
    {
        $args = JobQuery::getCarouselArgs(-1);
        $query = new \WP_Query($args);

        $result = $this->jobRepository->queryCard($args);
        $jobs = $result['jobs'] ?? [];

        return [
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ];
    }
}