<?php

/**
 * Single Job Template
 *
 * Displays detailed information about a job listing.
 */

use WPLokerBJM\Core\Container;

get_header();
Container::getContainer()->get(\WPLokerBJM\Views\Page\SingleView::class)->render($post->ID);
get_footer();
