<?php

/**
 * Single Job Template
 *
 * Displays detailed information about a job listing.
 */

namespace AstraChild;

use AstraChild\Core\Container;
use AstraChild\Views\Page\SingleView;

get_header();

$post_id = get_the_ID();
$singleView = Container::getContainer()->get(SingleView::class)->render($post_id);

get_footer();
