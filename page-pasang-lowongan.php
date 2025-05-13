<?php

/**
 * Template Name: Pasang Iklan Loker
 */

use AstraChild\Core\Container;
use AstraChild\Views\Page\PasangIklanView;

get_header();

$iklanView = Container::getContainer()->get(PasangIklanView::class)->render();

get_footer();
