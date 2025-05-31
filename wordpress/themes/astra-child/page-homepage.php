<?php
/**
 * Template Name: Homepage Lowongan
 */
namespace AstraChild;

use AstraChild\Core\Container;
use AstraChild\Views\Page\HomepageView;

get_header();
$homepageView = Container::getContainer()->get(HomepageView::class)->render();
get_footer();