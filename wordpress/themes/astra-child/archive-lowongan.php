<?php

/**
 * The template for displaying Lowongan archives and search/filter
 *
 * @package Astra-Child
 */


namespace AstraChild;

use AstraChild\Core\Container;
use AstraChild\Views\Page\ArchiveView;

get_header();

$archiveView = Container::getContainer()->get(ArchiveView::class)->render();

get_footer();
