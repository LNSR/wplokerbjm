<?php

namespace AstraChild\Controllers\REST;

use AstraChild\QueryBuilders\JobQuery;
use AstraChild\Resources\Components\JobCard;

class Carousel
{
    public static function handle(\WP_REST_Request $request)
    {
        $args = JobQuery::getCarouselArgs(-1);

        $query = new \WP_Query($args);

        $jobs = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $jobs[] = JobCard::getCardData(get_the_ID());
            }
            wp_reset_postdata();
        }

        return rest_ensure_response([
            'jobs' => $jobs,
            'totalJobs' => $query->found_posts,
        ]);
    }
}